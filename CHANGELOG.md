### buffer for next release

<!-- here add all issues that haven't been released yet -->
<!-- to release, assign a version number and move them there -->

## v1.8.2

- feat: accept aula-frontend-version header

## v1.8.1

- fix: broken roles of user when one of their rooms is deleted

## v1.8.0

- fix: put getUserGDPRData back to legacy User class
- test: generate random rooms and import users to the rooms using addAllCSV method

## v1.7.9

- fix(versions): github release workflow

## v1.7.8

- feat(versions): add API to get current running version of BE
- fix: getUsers filter by room hash id

## v1.7.7

- fix: CSV import failing
- fix: don't use transaction in addUserRole when importing from CSV

## v1.7.6

- fix(roles): delete lingering roles from users table when a room is deleted.
- fix(roles): fix race condition when multiple requests to update an user roles where being submitted.
- fix(password): prevent the creation of password with less than 12 characters.
- fix(password): User->resetPassword now return in the response the new temporary password created.

<!-- fix: validate email when creating a new User -->

## v1.7.5

- fix(roles): don't return rooms when the user has no roles on them.

## v1.7.1

- fix(email): add extra email headers to battle SPAM filters
- fix(csv): on CSV import, Role of the Users should always be "User", the Role should be specifically set only for the room(s) the Users are imported into

## v1.7.0

- fix(security): sanitize various params
- fix(security): prevent the use of extra_where by the frontend

## v1.6.0

- feat: allow tech admin to edit main room

## v1.5.6

- feat: add idea directly to a idea box

## v1.5.5

- fix(email): forgot password email template missing username
- feat(user): admins can reset password for users with email, too

## 1.5.4

- fix: editUser not working

## 1.5.3

- fix: Message->addMessage not working when using user hash id on target
- fix: CORS preflight requests (OPTIONS) failing
- fix: forgot password email doesn't contain instance code

## 1.5.2

- fix: instance config refactor issue with checking consent

## 1.5.1

- fix: instance code checks cause failures (ex: user_consent.php)
- fix(cron): update example config with safe array access
- fix(user count in quorum): fix super user roles with voting rights being counted two times

## 1.5.0

- feat(csv): schedule sending registration emails at a later point
- feat(user): admins can reset password for a given user (users without email only)

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
