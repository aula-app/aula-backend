### buffer for next release

<!-- here add all issues that haven't been released yet -->
<!-- to release, assign a version number and move them there -->

## 1.4.3

- ref(cron): system of scheduling commands is refactored for better extensibility

## 1.4.2

- fix(email): change Reply-To to support email instead of automated email sender address
- fix(csv): import CSV of users and assign them to multiple Rooms

# 1.4

- fix(security): don't leak exceptions to the API responses, instead log them as errors
- ref: email templates using named parameters https://github.com/aula-app/aula-backend/pull/243

# 1.3

- fix: duplicate users [issue 1](https://github.com/aula-app/aula-frontend/issues/625) and [issue 2](https://github.com/aula-app/aula-frontend/issues/620)
- fix: [can't sign on new user](https://github.com/aula-app/aula-backend/issues/232)
- fix: [upload profile photo issue](https://github.com/aula-app/aula-backend/pull/234)

# 1.2

- Feature #167: Implement Dockerization of the repo.
- Fix https://github.com/aula-app/aula-frontend/issues/601: count of voting users in a room is not correct
