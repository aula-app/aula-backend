# Usage of laravel-data

[Docs of spatie/laravel-data](https://spatie.be/docs/laravel-data/v4/introduction)

The Laravel data object, UserData, now doubles as "InputDTO plus validation with typecasting" and "OutputDTO/Resource".
It works and is neat (i.e. not verbose) to have it all fairly centralized in one placed.

Caveats + notes:

**Create-with-defaults vs. Update/Put-all-required**

- we use nullable/`|null` to communicate which fields are not always required, then use inheritance (`UserStoreData`, `UserUpdateData`) to set `required`, `sometimes` (= not required) or `missing` (= never)  for validation.
    - we do not use `Optional` since it adds complexity
      (e.g. the validation rule `sometimes` is then inferred and hard to override, even with `required`)
- `UserData` is the parent with *all* properties/fields
    - all properties are readonly
    - child classes can not extend with new fields - this causes a cascade of verbosity (verbatim repeated constructors etc.)
    - inheritance forces childrens' properties' types to be subsets of parents' --
      this enforces more consistency than repeating properties over several DTOs
    - every field/property that can be nullable/unrequired in any child…
        - …has to be nullable in the parent.
        - all properties have to be `public`
          (any combination of protected or private just did not work)
        - is implemented as `abstract` and "hooked"
          (t has a `get;`; this also makes it readonly)
        - all abstract properties have to be finalized in all children
        - in the child, the property can be overriden with a subset of typehints,
          notably without `|null`.
        - Psalm complains about this subset; but its warning about compiler errors don't apply.
        - We can not completely hide/protect any parent's property;
          instead we rely on validation (e.g. to prevent (write) attempt to `UserStoreData->hash_id`)
        - this construct is slightly cumbersome. Other "cleaner" attempts (`protected`, child properties) did not pan out and led to substantially more code.
- `UserModelData` serves as a domain model and for output (via response).
    - properties can be removed from output (e.g. `id`) via (undocumented) `#[Spatie\LaravelData\Attributes\Hidden]`

**Field name mapping**

Fields (like `about_me`) have 4 versions, where 3 happen to be identical: in Request (API Input, `about_me`), in UserData (`aboutMe`), in LegacyUser (`about_me`) and Response (API Output, again `about_me`).  
For field name mapping, Laravel-data offers `MapInputName` and `MapOutputName` (or `MapName` if they're identical). 

- MapInputName handles two cases:
    1. Request → UserData (implicit, magic `::from`)
    2. LegacyUser → UserData (explicit call to `::from`)
- MapOutputName handles serialization only:  
  UserData→Response
- the other, fourth mapping UserData→LegacyUser is handled manually in the Store+Update UseCases

This works right now because Requests and LegacyUser have synonymous fields. (The implicitness/magic hides risk.)
But this could break if we rename any "side" independent of the other, and thus have a solution that MapInput/OutputName don't cover.

- It won't break as long as the mappings are simple and/or consistent (like now; or always snake/camel)
- We could still provide explicit mapping for either or both sides, should it be needed.
    - e.g. by implementing more explicit ::from, like fromModel and fromRequest ...called [magic creation](https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/creating-a-data-object#content-magical-creation).
      This seems to lead to much repeated code; negating the benefits of laravel-data.
    - there is a yet-to-be-implemented [spec](https://github.com/spatie/laravel-data/blob/main/specs/mapping-scopes.md) for MapInput scopes which would solve this problem
- This still muddles concerns (as noted by @aivuk): UserData has to know about Requests. Arguably, it kind of has to, to do its job as a centralized one-thing-does-all.

**Summing up**

- laravel-data works, neatly, with caveats. We have to be careful with fieldnames, not get lost in the magic and implicitness.
- alternative: use Laravel Request validation, explicit input & output DTOs that do the mapping and casting. Much more verbose.

