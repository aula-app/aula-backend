### buffer for next release

<!-- here add all issues that haven't been released yet -->
<!-- to release, assign a version number and move them there -->

- feat(csv): schedule sending registration emails at a later point

## 1.4.7

- fix(csv): enable csv uploads for tech_admin

## 1.4.6

- feat(cron): deactivate bad commands
- chore(cron): run scheduled commands every minute

## 1.4.5

- fix(csv): username empty in CSV mitigations (smart naming or fail CSV upload)
- fix(csv): add user to standard room & other rooms (properly)
- fix(commands): exception in one instance can stop processing others

## 1.4.4

- fix(cron): redirect logs to docker
- fix(cron): run every 15 minutes (set instance online status, sending out emails)

## 1.4.3

- ref(cron): system of scheduling commands is refactored for better extensibility
- fix(cron): for CSV imported users, welcome emails should be sent

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
