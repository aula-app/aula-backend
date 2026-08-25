# Migrating an Existing School onto an Identity Provider

How a school that already uses aula, with accounts, rooms and content of its
own, starts syncing its users and classes from an identity provider.

This is the counterpart to [idp-directory-sync.md](idp-directory-sync.md), which
covers a school that starts on the provider from day one. The two differ in one
way that changes everything: here, **the people already exist on both sides**,
and nothing identifies them to each other.

## The problem

A greenfield school has nothing to collide with, so the first SSO login can
import everything and be done. An existing school has:

- aula accounts with passwords, ideas, votes and comments
- rooms with history
- no shared identifier with the provider: Eduplaces exposes **no email**, and
  aula stores nothing the provider knows about

So the two sides can be matched by **name** or by **proof** of an aula password,
and nothing else. A wrong name match hands one account and its content to
another person, so no name match is applied without review.

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
| `null`       | not migrating; a tenant with no prior aula use                  |
| `flagged`    | operator has enabled it; admin has not connected yet            |
| `connected`  | admin's account is linked; `idp_school_id` known                |
| `reviewing`  | a proposal exists and is waiting on the admin                   |
| `importing`  | proposal applied; the import job is running                     |
| `linking`    | import done; users are linking themselves as they log in        |
| `completed`  | operator has finalised; behaves like any synced school          |

`isMigratingToIdp()` is true while the value is non-null and not `completed`,
and that is what stops `bootstrapIdpTenant()` firing.

## Safety rules

These are the things that go wrong silently, so they are stated as rules.

**`bootstrapIdpTenant()` must not fire on a migrating tenant.** Its own guard,
that no user has an `sso_sub`, is true of every password-only school, so the
first login through the provider would claim the school's admin account. Two
further guards apply:

- decline while `idp_migration_status` is non-null
- decline when the tenant holds any user beyond `admin1_username` and
  `admin2_username`, which means the school is already in use

**`sso_required` must stay off until the migration completes.** Linking needs a
password login, and `LegacyLoginController` refuses password login for the whole
tenant when `sso_required` is set. Turning it on early leaves the remaining
accounts unlinkable: they match nothing at the provider and cannot log in
locally. Finalising is therefore an explicit operator step, gated on the
progress screen.

**An unmatched login must have a way through.** A pupil with no aula account has
to be able to say so and be provisioned, or the link prompt repeats forever. The
cost is that a login that does have an account can take that exit and orphan it,
which is why the admin gets a list of accounts that never linked.

## Matching

### Rooms

Provider group to existing room by **normalised name**, through `NameKey`: trim,
collapse spaces, lower case, fold umlauts. Reviewed like users, since a wrong
room merge hands a whole class access to an existing room's content and history.

Unmatched groups become new rooms. Existing rooms that match nothing are left
alone and stay unmanaged.

### Users

Matched on **full name**, with two complications that the review must surface
rather than hide.

**Not everyone has a real name.** With `users:read` but not `people:read`, the
provider returns `pseudonym` ("Denk Raumfahrer") for a user and a real `name`
only inside a group's member list. So:

| directory user is…    | name available | matchable |
|-----------------------|----------------|-----------|
| in at least one group | real name      | yes       |
| in no group           | pseudonym only | **no**    |

A pseudonym-only row is recorded with `idp_name_kind = pseudonym`, carries no
name keys, and can be paired by hand only.

**Names are not unique.** Two rows called "Max Müller" on either side make an
`ambiguous` outcome, never a `confident` one.

Candidates are compared against aula `realname` and `displayname`, and against
both provider first-name forms, `firstFull` and `firstCall` ("Wilma Johanna
Sophie" going by "Johanna"). Normalisation as for rooms.

Outcome per candidate:

| outcome     | when                                   | default in review |
|-------------|----------------------------------------|-------------------|
| `confident` | exactly one match on both sides        | checked           |
| `ambiguous` | more than one candidate either way     | unchecked, flagged |
| `none`      | no match, or provider side has no name | unchecked          |

## The review screen

`Settings → Sync with Eduplaces`, visible to admins only.

Three sections, rooms above users:

1. **Proposed merges**: `aula name` · `provider name` · `merge?`
   A checkbox for `confident` rows and a **dropdown** for the rest, so an admin
   can pair a pseudonym-only row or correct a wrong pairing. A checkbox alone
   makes both impossible.
2. **In aula only**: keeps password login and can link later
3. **In the provider only**: created as new accounts

Search, paging and "check all confident" are needed from the start: a
thousand-row proposal is unusable unpaged.

The proposal is **stored**, not recomputed on submit. A review can take an hour
and the directory can change underneath it, so applying a pairing the admin
never saw is exactly what this screen prevents.

## What a merge does

```
aula row:   idp_user_id  ← provider id
            sso_sub      ← unchanged (set at their first SSO login)
```

Nothing else. The account keeps everything it held and gains SSO, and password
login goes on working until its owner signs in through the provider, which is
what lets a migration run for weeks without disrupting anyone.

If `SchoolImport` already created a row for that directory user, the merge
**absorbs** it: an imported row carries no content, so `idp_user_id` moves to
the existing account and the imported row is deleted.

## Linking at login

For a tenant in `linking` (or earlier), an SSO login that finds no
`idp_user_id` match is sent to the existing account-link flow rather than
provisioning:

```
callback → offerAccountClaim → /login?sso_error=account_link_required&sso_link=…
        → the aula password is proved
        → POST /sso/link stamps sso_sub + idp_user_id on that row
```

This shares the link flow an email match uses, which a provider exposing no
email can never reach.

**No JWT is issued before linking.** Signing the user in first and asking
afterwards would make dismissing the prompt the cheapest path and leave the
duplicate in place.

## Progress

The settings screen reports:

- **linked**: `idp_user_id IS NOT NULL`
- **not yet linked**: listed by name, and the migration to-do list
- **directory users with no aula account**: created on their first login

The middle list is what makes "are we done?" answerable, and it gates
finalisation.

## Out of scope

- Merging two aula accounts with each other
- Moving content between accounts, which an imported row never has
- Automatic deactivation of accounts that never link: listed, decided by an admin

## Choices worth knowing

- **Rooms are reviewed, not matched automatically.** A wrong room merge exposes
  existing content to a whole class.
- **The review offers a dropdown, not a checkbox alone.** Without it a
  pseudonym-only row cannot be paired and a wrong pairing cannot be corrected.
- **Admin means `userlevel >= 50`.** Principal (44/45) is excluded; widening it
  is a one-line change.
- **An account that never links is left alone**, listed and never touched
  automatically.
