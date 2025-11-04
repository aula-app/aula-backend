# to: Self-Hosting interested parties

We have looked into different options of enabling self-hosting of aula-Software. There are various tradeoffs between them, and due to our limited capacity we don't support all of them at the moment.

They vary in these dimensions:
1. Ease of use for you, the self-hoster
1. Functional parity (there might be features missing in some self-hosted options)
1. Dependency on aula-Hosted services (in some options we provide limited API)
1. Full ownership of users' data (in some options we process some users' data)

**Our recommendation is to self-host everything** on your own, which means [aula-backend](https://github.com/aula-app/aula-backend) (BE API), [aula-frontend](https://github.com/aula-app/aula-frontend/) (FE for web and Mobile Apps for Apple/Android) and database and other services. This is the most difficult solution for the self-hoster because it involves managing your own aula Mobile App (you'll need infrastructure for doing regular builds, configuration of the repository, management of Apple APNS and Google Push Notification accounts, etc.). However, this is the only solution that provides (2) full Functional parity, (3) no dependency on aula-Hosted services and (4) full ownership of users' data. You will write your own Privacy Policy, and there will be no need to have any contract between aula and your organisation.

**If you want to simplify** your work, and have (2) incomplete functional parity (for now this would mean you'd miss out on Push Notifications), (3) some dependency on aula-Hosted services and (4) aula gGmbH would process some limited data of your users, we have an alternative. You would host only [aula-backend](https://github.com/aula-app/aula-backend) (BE API) and the database, while your users will use our existing Mobile Apps for Android/Apple and our existing website. However, to redirect the App to communicate with your BE API, we'll need to let the Mobile Apps use aula's Central Registry of all instances to resolve your BE API URL. This means we will temporarily have access to the IP addresses of your users, therefore we'd process their Personally Identifiable Information. This means you as self-hoster and us as aula will need to establish a Data Processing Agreement where we outline which data is processed by whom. We'd also need to let the Mobile Apps show multiple Privacy Policies in an understandable way for our users.

There are a couple of other options in between these two extremes, but we would need to invest remarkable effort into supporting them, and they would still mean we'd need to have a DPA contract and you'd use some services provided by aula, just in a different way:
1. **We** could **process even more user data** to enable Push Notifications. This would be a lot of work for us, and you'd have to share even more data of your users with aula.
1. We could change the way we determine your BE API URL to use DNS. This would slightly improve Data Privacy, but wouldn't eliminate the need for contracts, and you'd still depend on aula's control of that service.

Note: At the moment, we haven't yet released aula version with the usage of Push Notifications, but it's planned for soon. Once we release this functionality, we will include instructions on how to configure [aula-frontend](https://github.com/aula-app/aula-frontend/) (containing source code for Mobile Apps) with your Google/APNS account data for Push Notifications and perform builds and releases.

Note: At the moment, we haven't yet released [aula-selfhosted](https://github.com/aula-app/aula-selfhosted) which will be easy-to-use Docker Compose based solution. We will update this notice as soon as that repository is ready for you use.

If you have any further questions, please reach out to `dev [at] aula [dot] de`
