# EuKnowledgeGraph

This repository contains the configuration of the Wikibase instance hosting the EU Knowledge Graph.

## Usage

- edit the `services_secrets.env` and `mysql_secrets.env` files with your credentials
- install docker compose
- run "docker-compose up"
- run "./script"

## Tip

To avoid your env files being committed by mistake, you can remove them (temporarily) from the index with:

`git update-index --assume-unchanged services_secrets.env mysql_secrets.env`
