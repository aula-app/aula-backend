# Migrating an Existing School onto an Identity Provider

How a school that already uses aula — with real accounts, rooms and content —
starts syncing its users and classes from an identity provider.

This is the counterpart to [idp-directory-sync.md](idp-directory-sync.md), which
covers a school that starts on the provider from day one. The two differ in one
way that changes everything: here, **the people already exist on both sides**,
and nothing identifies them to each other.

## The problem

A greenfield school has nothing to collide with, so the first SSO login can
import everything and be done. An existing school has:

- aula accounts with passwords, ideas, votes and comments
- rooms with history
- no shared identifier with the provider — Eduplaces exposes **no email**, and
  aula stores nothing the provider knows about

So the two sides can only be matched by **guessing** (names) or by **proof**
(the person's own password). Guessing wrong hands somebody another person's
account and content, so a guess may never be applied without review.

## Principles

1. **No automatic match is ever applied unreviewed.** Name matching proposes;
   a human decides.
2. **Merging is lossless and reversible.** A merge stamps `idp_user_id` onto an
   existing row. Nothing moves, nothing is deleted, because the provider side
   has no content.
3. **Nobody is locked out mid-migration.** Password login stays available until
   a person has actually linked.
4. **The admin can always tell how far along it is.** "Are we done?" must be
   answerable from a screen.

## The flow

```
1. aula-manager (Filament)   operator flags the tenant: will sync with <provider>
2. aula settings             admin sees "Sync with Eduplaces"
3.   ├─ Connect my account   admin signs in via the provider, links it to their
   │                         own aula admin account (password already proved by
   │                         being logged in). This learns idp_school_id.
4.   ├─ Prepare import       fetch groups + users, build a match proposal
5.   ├─ Review               table of proposed merges; admin edits and confirms
6.   └─ Apply                rooms created/linked, users merged or created
7. Everyone else logs in     matched users just work; unmatched are asked to
                             prove an aula password, or declare themselves new
8. Progress                  settings screen shows linked / not yet linked
9. Finalise                  operator turns on sso_required once nobody is left
```

Steps 4–6 reduce how many people reach step 7. Step 7 is what makes the
migration completable even when matching cannot work.

## Tenant states

`tenants.idp_migration_status`:

| value        | meaning                                                        |
|--------------|----------------------------------------------------------------|
| `null`       | not migrating — greenfield rules apply                          |
| `flagged`    | operator has enabled it; admin has not connected yet            |
| `connected`  | admin's account is linked; `idp_school_id` known                |
| `reviewing`  | a proposal exists and is waiting on the admin                   |
| `importing`  | proposal applied; the import job is running                     |
| `linking`    | import done; users are linking themselves as they log in        |
| `completed`  | operator has finalised; behaves like any synced school          |

The state is what suppresses greenfield behaviour: while it is non-null, the
first-login bootstrap does not fire.

## Safety rules

These are the things that go wrong silently, so they are stated as rules.

**The first-login bootstrap must not fire on a migrating tenant.** Today its
only guard is "no user has an `sso_sub`", which is true of every password-only
school. Left alone, the first provider user to sign in takes over the school's
real admin account. Two guards, both applied:

- skip the bootstrap entirely when `idp_migration_status` is non-null
- never take over an admin row that has a password — the greenfield admin is
  seeded with none, a real one always has one

**`sso_required` must stay off until the migration completes.** Linking needs a
password login, and `LegacyLoginController` refuses password login for the whole
tenant when `sso_required` is set. Turning it on early makes the remaining users
unlinkable — they match nothing on the provider side and cannot log in locally.
Finalising is therefore an explicit operator step, gated on the progress screen.

**Unmatched people must have a way through.** A pupil with no aula account must
be able to say so and be provisioned normally, or they loop forever. The cost is
that someone who *does* have an account can take that exit and orphan it, which
is why the admin gets a list of accounts that never linked.

## Matching

### Rooms

Provider group → existing room by **normalised name** (trim, collapse spaces,
case-insensitive, umlauts folded). Reviewed like users: a wrong room merge is
worse than a wrong user merge, because it hands a whole class access to an
existing room's content and history.

Unmatched groups become new rooms. Existing rooms that match nothing are left
alone and stay unmanaged.

### Users

Matched on **full name**, with two complications that the review must surface
rather than hide.

**Not everyone has a real name.** With `users:read` but not `people:read`, the
provider returns `pseudonym` ("Denk Raumfahrer") for a user and a real `name`
only inside a group's member list. So:

| person is…            | name available | matchable |
|-----------------------|----------------|-----------|
| in at least one group | real name      | yes       |
| in no group           | pseudonym only | **no**    |

Pseudonym-only people appear in the review as unmatchable and can only be mapped
by hand.

**Names are not unique.** Two people called "Max Müller" on either side is an
ambiguous match, never a confident one.

Candidates are compared against both aula `realname` and `displayname`, and
against both provider first-name forms (`firstFull` and `firstCall` — "Wilma
Johanna Sophie" is called "Johanna"). Normalisation as for rooms.

Outcome per candidate:

| outcome     | when                                   | default in review |
|-------------|----------------------------------------|-------------------|
| `confident` | exactly one match on both sides        | checked           |
| `ambiguous` | more than one candidate either way     | unchecked, flagged |
| `none`      | no match, or provider side has no name | unchecked          |

## The review screen

`Settings → Sync with Eduplaces`, visible to admins only.

Three sections, rooms above users:

1. **Proposed merges** — `aula name` · `provider name` · `merge?`
   Checkbox for confident rows; a **dropdown** for the rest, so an admin can map
   a pseudonym-only person or correct a wrong guess. A checkbox alone makes
   those two cases impossible.
2. **In aula only** — will keep password login and can link later
3. **In the provider only** — will be created as new accounts

Needs search, paging and "check all confident" from the start: a thousand-pupil
school makes an unpaged table unusable.

The proposal is **stored**, not recomputed on submit. The admin may take an hour
and the directory may change underneath; applying something they did not see is
the exact mistake this screen exists to prevent.

## What a merge does

```
aula row:   idp_user_id  ← provider id
            sso_sub      ← unchanged (set at their first SSO login)
```

Nothing else. The person keeps everything they had and gains SSO. Password login
keeps working until they actually sign in through the provider, which is what
allows a migration to run for weeks without disrupting anyone.

If the import already created a row for that person, the merge **absorbs** it:
provider-created rows have no content by construction, so the id moves to the
real account and the empty row is deleted.

## Linking at login

For a tenant in `linking` (or earlier), an SSO login that finds no
`idp_user_id` match is sent to the existing account-link flow rather than
provisioning:

```
callback → storeLinkIntent → /login?sso_error=account_link_required&sso_link=…
        → user proves their aula password
        → POST /sso/link stamps sso_sub + idp_user_id on their row
```

All of that already exists; only the trigger changes. Today it fires on an email
match, which can never happen with a provider that exposes no email.

**No session is issued before linking.** Logging someone in first and then
asking makes dismissing the prompt the path of least resistance, and produces
the duplicate silently.

## Progress

The settings screen reports:

- **linked** — `idp_user_id IS NOT NULL`
- **not yet linked** — listed by name; this is the migration to-do list
- **provider people with no aula account** — will be created on first login

The middle list is the deliverable. It is what makes "are we done?" answerable,
and it gates finalisation.

## Out of scope

- Merging two aula accounts with each other
- Moving content between accounts (never needed: provider rows have none)
- Automatic deactivation of accounts that never link — surfaced, decided by a human

## Decisions taken without an explicit answer

Recorded because they were recommendations, not instructions, and are cheap to
reverse:

- **Rooms are reviewed, not auto-matched.** A wrong room merge exposes existing
  content to a whole class.
- **The review offers a dropdown, not only a checkbox.** Otherwise pseudonym-only
  people cannot be matched at all and wrong guesses cannot be corrected.
- **Admin means `userlevel >= 50`.** Principal (44/45) is excluded; widening it
  later is a one-line change.
- **Accounts that never link are left alone**, listed but never touched
  automatically.
