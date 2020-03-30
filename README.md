A tool that will output the diff between two databases given a list of tables

## Before Running:

- create a list of tables to syncronize

- alter each table in the list to have a rowversion column named `SYNC_VERSION`(This is MasterDB)

- create a second database with all the tables from the list (This is copyDB)

- alter each table in copyDB by adding an integer column named `SYNC_LAST_VERSION`

- modify `docker-compose.yml` with the correct values for the environment variables

- add your list of table names to src/start.php

## Run with docker:

```
$ docker-compose up app
```
