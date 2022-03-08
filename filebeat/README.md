# Sending Platform.sh logs to Logz.io using Filebeat

## The problem

Platform.sh log files are stored on disk (in `/var/log`) and trimmed
to 100 MB automatically. For busy environments this means that logs
may only contain entries for the last 24 hours or even less depending
on the volume of log entries an environment is generating.

Platform.sh _dedicated_ hosting plans support sending logs to a
remote logging service such as Loggly, Papertrail, or Logz.io
using the rsyslog service. **But this option is not available
for _grid_ hosting plans.**

## Bulk uploading logs

A possible solution is to bulk upload log files to remote storage or
to a log managment solution.

[Platform.sh recommend bulk uploading logs to remote storage (like
an Amazon S3 Bucket) via a cron job](
https://docs.platform.sh/development/logs.html#container-logs).

Many log management services such as Papertrail, Loggly and Logz.io
also support bulk upload of log files via http and it would be possible
to automate this via cron and using cURL.

### Bulk upload limitations

- Many log managment services set a limit on the size of files that
  can be uploaded.
- Bulk upload needs to occur on a set schedule. If log events start
  to occur more rapidly than normal, there is the possibility that
  older log entries may be lost because they have been trimmed
  before a scheduled bulk upload is timed to occur.

## Forwarding (streaming) logs using Filebeat

Many log management services support various ways of forwarding logs
in real-time as they are written to. Many services provide their
own shipping agents that run as a service on the host OS. For
Platform.sh environments, installation of services is not an option.

[Filebeat](https://www.elastic.co/beats/filebeat) is a light-weight log
shipping tool written in Go that can be used to forward logs to a
number of log management solutions that are based on the
[ELK stack](https://www.elastic.co/what-is/elk-stack).

"ELK" is the acronym for three open source projects: Elasticsearch,
Logstash, and Kibana. Elasticsearch is a search and analytics engine.
Logstash is a server‑side data processing pipeline that ingests data
from multiple sources simultaneously, transforms it, and then sends
it to a "stash" like Elasticsearch. Kibana lets users visualize data
with charts and graphs in Elasticsearch.

Filebeat can watch multiple log files, harvest new entries, and
forward them to a remote ELK services.  If it is interrupted (for
example if the environment goes off-line), it remembers
the location of where it left off and then continues from there when
everything is back online.

Crucially, Filebeat can be downloaded via the
build step of platform.sh environment, deployed to a writeable mount,
and then triggered to run as a background process by cron.

## 1. Drupal project structure

A `filebeat/` directory is placed at the same level as the `.platform/`
directory and the `.platform.app.yaml` file (both of which should be
outside of the publicly accessible web/ or public_html/ directories).

The `filebeat/` directory contains the main filebeat configuration file
`filebeat.yml` and a `scripts/` directory containing a number of
shell scripts needed to install, configure and get filebeat running via
cron.

```
├── .circleci/
├── .platform/
│   ├── solr_config
│   ├── routes.yaml
│   └── services.yaml
├── config/
├── drush/
├── filebeat/
│   └── scripts/
│   │   ├── config.sh
│   │   ├── cronjob.sh
│   │   └── install.sh
│   ├── filebeat.yml
│   └── README.md
├── private/
├── vendor/
├── web/
├── .platform.app.yaml
├── composer.json
├── composer.lock
├── LICENSE
├── phpcd.sh
└── README.md
```

## 2. `.platform.app.yaml`: create writable mount for filebeat

Filebeat needs to write to disk in order to run. So we need to create a
writeable mount in `.platform.app.yaml` where filebeat will be able to
run from.

```
mounts:
  '/.filebeat':
    source: local
    source_path: 'filebeat'
```

## 3. `.platform.app.yaml`: modify the build hook

Modify the build hook to add lines that will run an install script
which downloads filebeat. This runs on every build until a project
variable LOGZ_CONFIG is set to TRUE.

```yaml
hooks:
  build: |
    set -e
    # Download filebeat to send logs to Logz.io.
    echo "Logz.io filebeat configuration"
    if [ "$LOGZ_CONFIG" != "TRUE" ]; then
      echo "Running filebeat install script. Set environment variable LOGZ_CONFIG to TRUE to skip this step."
      bash filebeat/scripts/install.sh
    else
      echo "Skipping filebeat install script (LOGZ_CONFIG=TRUE)"
    fi
```

The install script located in `filebeat/scripts/` downloads filebeat
and a certificate filebeat needs to connect via https to Logz.io.
Files are downloaded to `filebeat/build/` since it is writable during
the build hook.

```shell
# filebeat/scripts/install.sh

#!/usr/bin/env bash

TEMP_BEAT_HOME=filebeat/build

[ ! -d $TEMP_BEAT_HOME ] && mkdir -p $TEMP_BEAT_HOME
cd $TEMP_BEAT_HOME

echo "Created ${TEMP_BEAT_HOME}"

# Install Filebeat
curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-8.0.0-linux-x86_64.tar.gz
tar xzvf filebeat-8.0.0-linux-x86_64.tar.gz
rm filebeat-8.0.0-linux-x86_64.tar.gz

echo "Downloaded Filebeat"

# Download the certificate
curl https://raw.githubusercontent.com/logzio/public-certificates/master/AAACertificateServices.crt --create-dirs -o pki/tls/certs/COMODORSADomainValidationSecureServerCA.crt

echo "Downloaded Logz.io certificate"
```

## 3. `.platform.app.yaml`: modify the deploy hook

Modify the deploy hook:

```
  deploy: |

    # Copy filebeat files to filebeat mount if its empty.
    if [ ! "$(ls -A .filebeat)" ]; then
      echo "Copying filebeat build to filebeat mount"
      bash filebeat/scripts/config.sh
    fi
    # Copy filebeat.yml config file to filebeat mount.
    echo "Copying filebeat.yml to filebeat mount"
    cp filebeat/filebeat.yml .filebeat/

```

The deploy hook runs the configuration script shown below if the
`.filebeat` mount is empty. The script just copies filebeat files
created during the build hook into the `.filebeat` mount. The
script also creates a registry directory which filebeat uses to
maintain state of log files it is harvesting.

```shell
# filebeat/scripts/config.sh

#!/usr/bin/env bash

# Move filebeat to mount with write access
cd $PLATFORM_HOME
cp -r filebeat/build/filebeat-8.0.0-linux-x86_64/* .filebeat/
mkdir .filebeat/registry
```

The deploy hook also copies the `filebeat.yml` file shown below from
the main `filebeat/` directory into the `.filebeat/` mount. This is
filebeat's main configuration file that correctly configures filebeat
to send logs to Logz.io.

```yaml
############################# Filebeat #####################################

filebeat.inputs:
- type: log
  paths:
    - /var/log/*.log
  fields:
    logzio_codec: plain
    token: ${LOGZ_TOKEN}
    type: nginx
  fields_under_root: true
  encoding: utf-8
  ignore_older: 0
  tail_files: true

#For version 6.x and lower
#filebeat.registry_file: /var/lib/filebeat/registry

#For version 7 and higher
filebeat.registry.path: /app/.filebeat/registry

#The following processors are to ensure compatibility with version 7
processors:
- rename:
    fields:
     - from: "agent"
       to: "beat_agent"
    ignore_missing: true
- rename:
    fields:
     - from: "log.file.path"
       to: "source"
    ignore_missing: true

############################# Output ##########################################

output:
  logstash:
    hosts: ["listener.logz.io:5015"]
    ssl:
      certificate_authorities: ['/app/filebeat/build/pki/tls/certs/COMODORSADomainValidationSecureServerCA.crt']
```

## 4. `.platform.app.yaml`: add cron job to start filebeat in the background

```yaml
crons:
  # Run script to start filebeat (if its not running).
  filebeat:
    spec: '*/5 * * * *'
    cmd: 'cd /app/filebeat/scripts ; ./cronjob.sh'
```

The cron job runs the following script every 5 mins.  The script checks
to see if a filebeat process is running. If not, it starts filebeat as
a background process.  It is important that filebeat is run as a
background process because otherwise the cron job would run
indefinitely and
[block redeployments of the environment](https://docs.platform.sh/development/troubleshoot.html#cron-jobs).

```shell
#!/usr/bin/env bash

# Run filebeat if not running already.
if ! pgrep -x "filebeat" >/dev/null; then
  cd /app/.filebeat;
  ./filebeat run &>/dev/null & disown;
fi
```

## 6. Create LOGZ_TOKEN environment variable

Obtain an account token from Logz.io. Then create a project environment variable
to store the token securely. The `filebeat.yml` file will reference this token
in order to connect to the correct account on Logz.io.

You set environment variables for a project environment using [console.platform.sh](https://console.platform.sh)
or you can use the CLI.  Note that setting environment variables causes an
environment to redeploy for changes to take effect.

```shell
platform variable:create -l environment -e [ENVIRONMENT_NAME] --prefix env: --name LOGZ_TOKEN --value '[LOGZ_IO_TOKEN]' --visible-runtime true --inheritable false --sensitive true
```

## 7. Commit changes and deploy to Platform.sh

Filebeat will be downloaded via the build hook and then copied over,
along with the `filebeat.yml` file to the `.filebeat` mount via the
deploy hook. Cron will then run filebeat in the background. Filebeat
will detect changes to log files within `/var/log` and ship those
differences to Logz.io.

## 8. Verify filebeat is running and logs are being received by Logz.io

If filebeat is running after deployment completes, you'll very
quickly start to see log entries arrive in Logz.io's Kibana
interface. If you don't, there are a number of things to check.

### Check filebeat process is running

SSH onto the environment and run the following command to check if filebeat is running:

```shell
$ pgrep filebeat
```

The command above should return the process ID (PID) for filebeat if it is
running. If filebeat is not running, the command returns nothing.

### Check filebeat cron job has run

The following platform cli command will list cron jobs that have run on the environment

```shell
$ platform activity:list
Warning:
An API token is set. Anyone with SSH access to this environment can read the token.
Please ensure the token only has strictly necessary access.

Activities on the project NIDirect-D8 (wcjm3mu7bacfm), environment logging (type: development):
+---------------+-------------+--------------+----------+----------+---------+
| ID            | Created     | Description  | Progress | State    | Result  |
+---------------+-------------+--------------+----------+----------+---------+
| f4su7zspwbzhk | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 14:34:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| xgvhxfh256yng | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 14:29:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| n63u726wjnxyy | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 14:24:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| mm6ii7odfzpue | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 14:19:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| tnu3n7mgmh4yc | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 14:14:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| c56hayvta46jw | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 14:09:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| ixntyh6vwi4xc | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 14:04:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| knes7qknkcxq2 | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 13:59:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| nvpv642zzwusi | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 13:54:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
| zzqimtht6jegu | 2022-03-08T | Platform.sh  | 100%     | complete | success |
|               | 13:49:21+00 | Bot ran cron |          |          |         |
|               | :00         | filebeat     |          |          |         |
+---------------+-------------+--------------+----------+----------+---------+
```

### Check filebeat's logs

Filebeat creates and rotates its own log files as it runs in
`.filebeat/logs/` and these may help to troubleshoot problems.

## 9. After installation, define a project variable to indicate filebeat is installed

To ensure that the installation in the build hook does not run on every
build, add a project environment variable to the project that will be
visible during the build process that will keep this from happening.

```shell
$ platform variable:create -l environment -e [ENVIRONMENT_NAME] --name LOGZ_CONFIG --value 'TRUE' --visible-build true
```

## Further information

- [Filebeat Reference](https://www.elastic.co/guide/en/beats/filebeat/current/index.html)
- [Logz.io general guide to shipping logs with Filebeat](https://docs.logz.io/shipping/log-sources/filebeat.html)
- [Platform.sh Community: How to forward Platform.sh logs to Logz.io](https://community.platform.sh/t/how-to-forward-platform-sh-logs-to-logz-io/197)


