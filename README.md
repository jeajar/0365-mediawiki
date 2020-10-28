# Mediawiki with Office 365 SSO #
Development branch, moving to Traefik v2 and automatic metadata refresh for SimpleSAML

## Overview ##
This docker image builds on top of the official [Mediawki image](https://hub.docker.com/_/mediawiki) version `1.35` and builds a list of useful and required mediawiki extentions for Office 365 SSO to work with the latest [SimpleSAMLphp](https://simplesamlphp.org/download) version `1.18.8` installation.

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
You need a system with `docker-ce` installed.

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
If using other s3 compatible storage like linode or Digital Ocean, the var `AWS_ENDPOINT_URL` needs to be set.

## Configure Office365
In the Office365 Admin control:
`Azure Active Directory > App Registration > New registration`
Create a new app registration with the following setting:

* Accounts in this organizational directory only 

Copy the Application (client) ID to set the `MS_ENTITYID` environment variable prefixed by `spn:`. This needs to be set as a docker secret, see bellow.
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

## Change cron secret
Edit the `secret` field in the `mediawiki-o365/simplesamlphp/config/module_cron.php`. The secret is passed in clear text in a curl command, but should still be hard to guess and not shared. See notes bellow to configure cron after deploying the stack.

## Build the docker images
cd into the `mediawiki-o365` folder in the git repo and build the image:
```
docker build --tag mediawiki-o365:1.35a .
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
Then create the overlay network. Making sure to pass the ``--attachable`` flag. This is required to enable mysql restores.
```
docker network create -d overlay --attachable traefik-swarm
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

## Test and add automatic metadata refresher to cron
The `wiki365` image is configured to automatically update the IdP metadata with the [metarefresh module in SimpleSAMLphp](https://simplesamlphp.org/docs/stable/metarefresh:simplesamlphp-automated_metadata). Nagigate 
to the cron page and get the cron configuration, the URL is: https://wiki.${DOMAINNAME}/simplesaml/module.php/cron/croninfo.php. You will need admin priviledges to access this page. Copy the `hourly` command to the system's crontab with `crontab -e`. 
### Force metadata refresh
The curl command can also be used *ad-hoc* to force the metadata refresh, i.e. you need to do this when starting the services for the first time.

## MySQL restores
Use this command, replace the $vars witht the actual value. `docker run` can't use the docker secrets, leaving a blank space to bypass bash_history is a good practice here. 
```
$  docker run -e DB_SERVER=wiki_db -e AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY} -e AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY} -e DB_USER=root -e DB_PASS=${MYSQL_ROOT_PW} -e DB_RESTORE_TARGET=s3://mediawiki/1-Mon-dfz-wiki-mysql_backup.gz --network traefik-swarm  mysql-backup:0.10.0
```

## Wiki images volume backup and restore
Backup
```
docker run --rm -v wiki365_images:/source:ro busybox tar -czC /source . > wiki-images.tar.gz
```
Restore:
```
docker run --rm -i -v wiki365_images:/target busybox tar -xzC /target < wiki-images.tar.gz
```

## Cron jobs
Two cron commands should be added to the main user's crontab with `crontab -e`
SimpleSAMLphp metarefresh:
```
 01 * * * * curl --silent "https://wiki.difuze.com/simplesaml/module.php/cron/cron.php?key=Passably-Bride-Omit5&tag=hourly" > /dev/null 2>&1
```
And the wiki images docker volume backup to s3 (Use whatever s3 path you set earlier)
```
0 1 * * * docker run --rm -v wiki365_images:/source:ro busybox tar -czC /source . > wiki-images.tar.gz && aws s3 cp wiki-images.tar.gz s3://wiki-difuze-db-backup/
```
The aws cli needs to be configured with the API key and secret with ```aws configure```