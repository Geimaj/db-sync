A tool used to synchronize a local mssql database with a remote postgres database

# How it works:

A server has a mssql database running on it. We refer to this as the local server and the master database. A second database is created on the local server and is referred to as the copy database.

Another server is running a Postgres database that we wish to keep in sync with all the data from the master database. This server is referred to as the destination server and the Postgres database is referred to as the remote database.

The copy db is used as a local representation of the remote postgres db so we have something to compare the master db with locally to see what has changed. A delta is calculated from master db and copy db, zipped and uploaded to the remote server via SFTP. A webservice is called on the remote server to start the processing of the new data. Once a successful response is recieved from the webservice the delta is applied to the copy db and all 3 databases are now in sync.

## Before Running:

-   create a list of tables to syncronize
-   alter each table from the list in MasterDB to have a rowversion column named `SYNC_VERSION`
-   create CopyDB as a clone of all the tables in the list from the masterDB
-   alter each table in copyDB by adding an integer column named `SYNC_LAST_VERSION` and remove rowversion column 'SYNC_VERSION'
-   add your list of table names to local-server/src/start.php

## Run local-server processing script with docker:

-   update docker-compose.json with the appropriate environment variables:
    -   MASTER_DB_HOST: 'local-server ip'
    -   MASTER_DB_NAME: 'master database name'
    -   MASTER_DB_USER: 'master database username'
    -   MASTER_DB_PASSWORD: 'master database password'
    -   COPY_DB_NAME: 'copy database name'
    -   SFTP_HOST: 'remote-server ip' (used to upload zip via SFTP)
    -   SFTP_PORT: 'remote-server SFTP port'
    -   SFTP_REMOTE_PATH: 'name of zip upload'
    -   SFTP_USER: 'SFTP username'
    -   SFTP_PASSWORD: 'SFTP password'
    -   PROCESS_OUTPUT_URL: 'url to call when upload via SFTP is complete'

```
$ docker-compose up app
```

## Run with test environment

```
$ docker-compose up
```
