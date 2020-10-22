# Mediawiki with Office 365 SSO #
Development branch, moving to Traefik v2 and automatic metadata refresh for SimpleSAML

## Overview ##
This docker image builds on top of the official [Mediawki image](https://hub.docker.com/_/mediawiki) version `1.34` and builds a list of useful and required mediawiki extentions for Office 365 SSO to work with the latest [SimpleSAMLphp](https://simplesamlphp.org/download) version `1.18.7` installation.

The docker compose stack uses Traefik `v2.3` , codename `picodon` as a reverse proxy and is configured to issue certificates autamatically with Let's Encrypt. If OV or EV certificates are a requirement, please refer to the [traefik documentation](https://docs.traefik.io/v1.7/configuration/backends/docker/). TLS 1.2 with a handlful of cyphers are allowed, older devices using TLS 1.1 will not work.

This image uses a modified entrypoint to handle docker secrets environement variables like the official mysql image do with variables like `MYSQL_ROOT_PASSWORD_FILE`.

__Required mediawiki extensions__
* [PluggableAuth](https://www.mediawiki.org/wiki/Extension:PluggableAuth)
* [SimpleSAMLphp](https://www.mediawiki.org/wiki/Extension:SimpleSAMLphp)

__Nice to have included in this image__
* [Math](https://www.mediawiki.org/wiki/Extension:Math)
* [MediaWikiLanguageExtensionBundle](https://www.mediawiki.org/wiki/MediaWiki_Language_Extension_Bundle)
* [Lockdown](https://www.mediawiki.org/wiki/Extension:Lockdown)

The [mysql-backup](https://github.com/databacker/mysql-backup) container connects to a s3 compatible endpoint and make regular database backups

### Requirements ###
You need a system with `docker-ce` and `docker-compose` installed.

## First steps and testing ##
First, clone this repository:
```
git clone git@gitlab.com:jmaxdfz/o365-mediawiki.git
```
All required environment variables resides in a  `.env` file. Passwords in environment variable __should not__ be used in production, see section bellow to set up docker secrets.

```
DOMAINNAME=example.com
TZ=America/Toronto
MYSQL_ROOT_PASSWORD=Secure Password, see not bellow regarding docker swarm and secrets
MYSQL_DATABASE=mediawiki
MYSQL_USER=mediawiki
BASE_URL=https://example.com
WIKE_NAME=Name of the Wiki
WIKI_PATH=/wiki
SIMPLESAML_PATH=/simplesaml
WIKI_EMAIL=wiki@example.com
FEDERATION_URL=https://login.microsoftonline.com/{UUID}/federationmetadata/2007-06/federationmetadata.xml
```
## Configure Office365
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
https://<example.com>/simplesaml/module.php/saml/sp/saml2-acs.php/default-sp
```

Next, we need the federation.xml document from our newly created web app. Click on endpoints button in the app dashboard to copy the URL for `Federation metadata document`. Set the environement varible `FEDERATION_URL` in the `.env` file with this URL.

Read the xml and check for entityID at the very top. Use this value to set the `MS_IDP` docker secret.

## Treafik setup
Comment out the line `caServer` on line `26` when ready to move into production.

## Build the docker images
cd into the `mediawiki-o365` folder in the git repo and build the image:
```
docker build --tag mediawiki-o365:${STACK_VERSION} .
```
Next, cd inside the `mysql-backup` folder and build the image
```
docker build --tag mysql-backup:0.10.0 .
```

## Security
Every usual steps should be done in order to secure the host and the host OS:
* Disable root user
* Disable ssh password login
* Use a high port like 32XXX instead of port 22 for SSH
* Turn on firewall and block all but ssh, http and https traffic
* Log all failed login on the server (HTTP or SSH)
* Perform CVE scans on host and on docker runtime

## Deployment with docker swarm
the file `stack.yml` should be used with the command `docker stack deploy`. The .env file needs to read to use the environment variable. This is a docker swarm limitation.

Ex command to remove and deploy while using the .env file:
```
docker stack rm wiki365 && export $(cat .env) && docker stack deploy -c stack.yml wiki365
```
A few steps are required before. First we need to initialize docker swarm and advertise on the loop back device only.
```
docker swarm init --advertise-addr lo:2377
```
Then create the overlay network
```
docker network create -d overlay traefik-swarm
```
To deploy this stack, we need to create docker secrets for the following sensible elements:
* mysql_root_password
* mysql_database
* mysql_user
* do_auth_token
* ms_entityid
* ms_idp
* aws_access_key_id
* aws_secret_access_key

Using the command:
```
 echo "my_secret_password_or_key" | docker secret create my_secret_name -
```

Using a space before `echo` to avoid writing to the bash history and having our sensible information in plain text on the host.
