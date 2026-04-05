---
name: site-deploy
description: "Deploy a web application via git pull, composer, and cache clear"
version: "1.0.0"
tools_required: ["bash", "git_operation", "composer_operation", "npm_operation", "laravel_artisan"]
---

# Site Deploy Skill

## Overview
Automates the deployment of a Laravel or generic PHP web application running on the server.

## Instructions

1. Ask the user for the target project path if not provided.
2. Run `git_operation` with operation `pull` to get latest code.
3. If it is a Laravel project (artisan file exists): run `composer_operation` install, then `laravel_artisan` with command `migrate --force`, then `laravel_artisan` with command `config:cache`, then `laravel_artisan` with command `route:cache`.
4. If a package.json exists: run `npm_operation` with operation `install`, then `npm_operation` with operation `run` and script `build`.
5. Report a summary of each step's outcome.

## Scripts

- `scripts/deploy.sh` — Full deploy script (alternative to step-by-step)

## Examples

User: "Deploy myapp"
Agent: Runs full deploy sequence for /var/www/myapp, reports each step.
