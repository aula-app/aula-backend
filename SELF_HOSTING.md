# to: Self-Hosting interested parties

## tl;dr;

aula self-hosted is NOT YET READY, but continue reading to understand whether you'd really like to self-host because of the technical, data privacy and legal caveats.

## self-hosting single aula instance

We have looked into different options of enabling self-hosting of aula-Software. There are various tradeoffs between them, and due to our limited capacity we don't support all of them at the moment.

They vary in these dimensions:

1. Ease of use for you, the self-hoster
2. Functional parity (there might be features missing in some self-hosted options)
3. Dependency on aula-Hosted services (in some options we provide limited API)
4. Full ownership of users' data (in some options we process some users' data)

**Our recommendation is to self-host everything** on your own by using not yet released [aula-selfhosted](https://github.com/aula-app/aula-selfhosted) which will be easy-to-use Docker Compose based solution. That means self-hosting [aula-backend](https://github.com/aula-app/aula-backend) (BE API), [aula-frontend](https://github.com/aula-app/aula-frontend/) (FE for web and Mobile Apps for Apple/Android) and database and other services. This is the most difficult solution for the self-hoster because it involves managing your own aula Mobile App (you'll need infrastructure for doing regular builds, configuration of the repository, management of Apple APNS and Google Push Notification accounts, etc.). However, this is the only solution that provides (2) full Functional parity, (3) no dependency on aula-Hosted services and (4) full ownership of users' data. You will write your own Privacy Policy, and there will be no need to have any contract between aula and your organisation.

**If you want to simplify your technical work** at cost of all other mentioned dimensions, and have (2) incomplete functional parity (for now this would mean you'd miss out on Push Notifications), (3) some dependency on aula-Hosted services and (4) aula gGmbH would process some limited data of your users, we have an alternative. You would host only [aula-backend](https://github.com/aula-app/aula-backend) (BE API) and the database, while your users will use our existing Mobile Apps for Android/Apple and our existing website. However, to redirect the App to communicate with your BE API, we'll need to let the Mobile Apps use aula's Central Registry of all instances to resolve your BE API URL. This means we will temporarily have access to the IP addresses of your users, therefore we'd process their Personally Identifiable Information. This means you as self-hoster and us as aula will need to establish a Data Processing Agreement where we outline which data is processed by whom. We'd also need to let the Mobile Apps show multiple Privacy Policies in an understandable way for our users.

There are a couple of other options in between these two extremes, but we would need to invest remarkable effort into supporting them, and they would still mean we'd need to have a DPA contract and you'd use some services provided by aula, just in a different way:

- **We** could **process even more user data** to enable Push Notifications. This would be a lot of work for us, and you'd have to share even more data of your users with aula.
- We could change the way we determine your BE API URL to use DNS. This would slightly improve Data Privacy, but wouldn't eliminate the need for contracts, and you'd still depend on aula's control of that service.

| benefits per type of self-hosting    | BE+DB (no Push, status quo) | BE+DB (no Push, with DNS) | BE+DB (aula Push API)         | BE+DB+Mobile Apps         |
| ------------------------------------ | --------------------------- | ------------------------- | ----------------------------- | ------------------------- |
| technical complexity of self-hosting | ✅ (low)                    | ✅ (low)                  | ✅ (low)                      | ⚠️ (Google/APNS, for now) |
| functional parity                    | ❌                          | ❌                        | ✅ (for now)                  | ✅                        |
| independent of aula-Hosted services  | ❌                          | ❌                        | ❌                            | ✅                        |
| types of user data shared with aula  | ⚠️ (IP address)             | ✅ (nothing, for now)     | ❌ (IP address, device token) | ✅ (nothing)              |
| no legal contracts necessary         | ❌                          | ❌                        | ❌                            | ✅                        |
| ready to use                         | ✅ (soon)                   | ⚠️ (changes necessary)    | ❌ (triaged, for now)         | ✅ (soon)                 |

_Note: At the moment, we haven't yet released aula version with the usage of Push Notifications, but it's planned for soon. Once we release this functionality, we will include instructions on how to configure [aula-frontend](https://github.com/aula-app/aula-frontend/) (containing source code for Mobile Apps) with your Google/APNS account data for Push Notifications and perform builds and releases._

_Note: At the moment, we haven't yet released [aula-selfhosted](https://github.com/aula-app/aula-selfhosted) which will be easy-to-use Docker Compose based solution. We will update this notice as soon as that repository is ready for you use._

To use our aula-Hosted solution, please visit [aula.de](https://aula.de).

In case you want to modify our solution, please bear in mind we release our open source aula repositories with EUPL v1.2-or-later license, which requires you to contribute back any modifications to us. Any modifications that you submit to us will by default be licensed under the same terms. We will make sure the configuration of aula-Software is simple for you and flexible enough to avoid any need to modify the code itself. In case you have a very specific need to change the code and not contribute back the changes to our code repositories, please reach out to us and we will decide on case-by-case basis whether to draft a special license agreement between us.

If you have any further questions, please reach out to `dev [at] aula [dot] de`

## self-hosting multiple aula instances

**Our recommendation is to self-host everything** on your own by using not yet released [aula-selfhosted](https://github.com/aula-app/aula-selfhosted) which will be easy-to-use Docker Compose based solution. That means you'll self-host [aula-backend](https://github.com/aula-app/aula-backend) (BE API), [aula-frontend](https://github.com/aula-app/aula-frontend/) (FE for web and Mobile Apps for Apple/Android) and database and other services. This is the most difficult solution for the self-hoster because it involves managing your own aula Mobile App (you'll need infrastructure for doing regular builds, configuration of the repository, management of Apple APNS and Google Push Notification accounts, etc.). However, this is the only solution that provides (2) full Functional parity, (3) no dependency on aula-Hosted services and (4) full ownership of users' data. You will write your own Privacy Policy, and there will be no need to have any contract between aula and your organisation.

_Note: At the moment, we haven't yet released aula version with the usage of Push Notifications, but it's planned for soon. Once we release this functionality, we will include instructions on how to configure [aula-frontend](https://github.com/aula-app/aula-frontend/) (containing source code for Mobile Apps) with your Google/APNS account data for Push Notifications and perform builds and releases._

_Note: At the moment, we haven't yet released [aula-selfhosted](https://github.com/aula-app/aula-selfhosted) which will be easy-to-use Docker Compose based solution. We will update this notice as soon as that repository is ready for you use._

_Note: At the moment, we haven't yet released [aula-manager](https://github.com/aula-app/aula-manager), which will be necessary for you to manage multiple instances._

In case you want to modify our solution, please bear in mind we release our open source aula repositories with EUPL v1.2-or-later license, which requires you to contribute back any modifications to us. Any modifications that you submit to us will by default be licensed under the same terms. We will make sure the configuration of aula-Software is simple for you and flexible enough to avoid any need to modify the code itself. In case you have a very specific need to change the code and not contribute back the changes to our code repositories, please reach out to us and we will decide on case-by-case basis whether to draft a special license agreement between us.

If you have any further questions, please reach out to `dev [at] aula [dot] de`
