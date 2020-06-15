# Mediawiki with Office 365 SSO #
## Overview ##
This docker image builds on top of the official [Mediawki image](https://hub.docker.com/_/mediawiki) version `1.34` and builds a list of useful and required mediawiki extentions for Office 365 SSO to work with the latest [SimpleSAMLphp](https://simplesamlphp.org/download) version `1.18.7` installation.

The docker compose stack uses Traefik `v1.7` , codename `morailles` as a reverse proxy and is configured to issue certificates autamatically with Let's Encrypt. If OV or EV certificates are a requirement, please refer to the [traefik documentation](https://docs.traefik.io/v1.7/configuration/backends/docker/). TLS 1.2 with a handlful of cyphers are allowed, older devices using TLS 1.1 will not work.

This image uses a modified entrypoint to handle docker secrets environement variables like the official mysql image do with variables like MYSQL_ROOT_PASSWORD_FILE.

__Required mediawiki extensions__
* [PluggableAuth](https://www.mediawiki.org/wiki/Extension:PluggableAuth)
* [SimpleSAMLphp](https://www.mediawiki.org/wiki/Extension:SimpleSAMLphp)

__Nice to have included in this image__
* [Math](https://www.mediawiki.org/wiki/Extension:Math)
* [MediaWikiLanguageExtensionBundle](https://www.mediawiki.org/wiki/MediaWiki_Language_Extension_Bundle)
* [Lockdown](https://www.mediawiki.org/wiki/Extension:Lockdown)

### Requirements ###
You need a system with `docker-ce` and `docker-compose` installed.

## First steps and testing ##
First, clone this repository:
```
git clone git@gitlab.com:jmaxdfz/o365-mediawiki.git
```
__See the section bellow on production deployment.__

The `docker-compose.yml` file uses a `.env` file that you need to create at the same level to set the following environment variables. Passwords in environment variable __should not__ be used in production.

```
DOMAINNAME=example.com
TZ=America/Toronto
DOCKERDIR="Path to local docker folder, I recommend ~/docker"
MYSQL_ROOT_PASSWORD=Secure Password, see not bellow regarding docker swarm and secrets
MYSQL_DATABASE=mediawiki
MYSQL_USER=mediawiki
BASE_URL=https://example.com
WIKE_NAME=Name of the Wiki
WIKI_PATH=/wiki
SIMPLESAML_PATH=/simplesaml
DO_AUTH_TOKEN=Digital Ocean API token. Needed for Traefik to issue certificates with Let's Encrypt. Should also be stored in a docker secret.
WIKI_EMAIL=wiki@example.com
MS_ENTITYID=spn:123c0d87-cc83-4d55-828e-05435090f2e1
MS_IDP=https://sts.windows.net/b9276a22-2145-4cc7-bbc6-609c776533c6/
```
## Configure Office365 ##
In the Office365 Admin control:
`Azure Active Directory > App Registration > New registration`
Create a new app registration with the following setting:

* Accounts in this organizational directory only 

Copy the Application (client) ID to set the `MS_ENTITYID` environment variable prefixed by `spn:`
```
Example:
MS_ENTITYID=spn:123a456-bb77-1234-828e-4353245h3245
```
Add the SimpleSAMLphp redirect URI in the `Authentification` section of the newly added app. Replace `<example.com>` with your wiki address
```
https://<example.com/simplesaml/module.php/saml/sp/saml2-acs.php/default-sp
```

Next, we need to download the federation.xml file from our app. Click on endpoints in the app dashboard to copy the URL for `Federation metadata document`. We'll need this later to create the metadata for SimpleSAMLphp.

For now, read the xml and check for entityID at the very top. Use this value to set the `MS_IDP` environment variable.

## Treafik setup ##
first, move the `traefik` folder in your `$DOCKERDIR`. Create the acme.json file and change permissions:
```
touch acme/acme.json
chmod 600 acme/acme.json
```
Modify the `traefik.toml` file with you domain name and email. Notice the line `37`, we'll use Let's Encrypt's staging CA to avoid blocking from the rate limit.

## Build the docker image ##
cd into the `mediawiki-o365` folder in the git repo and build the image:
```
docker build --tag mediawiki-o365:1.34 .
```
Next, cd at the root of the repo and run docker-compose. Make sure the `.env` file has all envirenment variables set
```
docker-compose up -d
```
## Convert Office 365 federation metadata for SimpleSAMLphp
Next we need to convert the federation.xml file from Office365 into a php metadata file SimpleSAMLphp can use. Log into the SimpleSAMLphp admin page, by default, the password is set to use the MYSQL_ROOT_PASSWORD environment variable. This can of course by changed later.

Go to: `https://<example.com>/simplesaml/` > `Federation` tab, then clic `XML to SimpleSAMLphp metadata converter`

Paste the XML content or upload the file then clic `Parse`. 

Copy the resulting php code into mediawiki-o365/simplesamlphp/saml20-idp-remote.php

## Note on passwords and Docker Compose ##
The proper way to deploy docker in production is to user docker swarm with external docker secrets to avoid passwords and sensible information in plain text or in environment variables.

## Security ##
Every usual steps should be done in order to secure the host and the host OS:
* Disable root user
* Disable ssh password login
* Use a high port like 32XXX instead of port 22 for SSH
* Turn on firewall and block all but ssh, http and https traffic
* Log all failed login on the server (HTTP or SSH)
* Perform CVE scans on host and on docker runtime

## Deployment with docker swarm ##
the file `stack.yml` should be used with the command `docker stack deploy` and __NOT__ with `docker-compose`

A few steps are required before. First we need to initialize docker swarm and advertise on the loop back device only.
```
docker swarm init --advertise-addr lo:2377
```
To deploy this stack, we need to create docker secrets for the following sensible elements:
* mysql_root_password
* mysql_database
* mysql_user
* do_auth_token
* ms_entityid
* ms_idp
* wiki_email

Using the command:
```
 echo "my_secret_password_or_key" | docker secret create my_secret_name -
```

Using a space before `echo` to avoid writing to the bash history and having our sensible information in plain text on the host.

For docker swarm, the `traefik` folder can stay in it's current location. Make sure to add the line `swarmmode = true` in the `[docker]` section. Also remmember line `37` for Let's Encrypt staging CA.

Then, the following environment variables can be set. Ideally in `/etc/environment` since `docker deploy` does not support `.env` files.
```
DOMAINNAME=example.com
TZ=America/Toronto
BASE_URL=https://example.com
WIKE_NAME=Name of the Wiki
WIKI_PATH=/wiki
SIMPLESAML_PATH=/simplesaml
```
