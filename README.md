# Casper Association Portal API

<p align="center">
    <img src="https://docs.casperlabs.io/icon/Casper_Wordmark_Red_RGB.png" width="400">
</p>

This is the backend API repo of the portal. To see the frontend webapp repo, visit <https://github.com/ledgerleapllc/CasperAssociationPortal>

It is a Laravel 8.0 application, with a MySQL database.

## Prerequisites

### Local

- Nginx (tested on 1.22 successfully)
- Mysql DB (tested on 8.0.26 successfully)
- PHP 8.1 (tested on 8.1.0 successfully)
  - gd, zip, mysqli, sqlite3, gmp and bcmath extensions
- Composer 2.5+
- Casper Client CLI utility (tested on 1.5.0 successfully)

Testing has been done on AWS EC2 medium instance running Ubuntu 20.

#### PHP

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt-get install -y php8.1
sudo apt-get install -y php8.1-{bcmath,bz2,intl,gd,mbstring,mysql,sqlite3,zip,common,curl,xml,gmp}
```

#### Composer

Install composer v2.5.0

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=2.5.0
```

#### NGINX

- Install it:

  ```bash
  sudo apt-get install nginx=1.22.1-1ubuntu1
  ```

- Copy default.conf to /etc/nginx/sites-available/default (You can change the /app folder if you want to use a different path)

  ```bash
  sudo cp default.conf /etc/nginx/conf.d/default.conf
  ```

- Remove /var/www/html directory **Do not do this if you are using that path for other sites**

  ```bash
  sudo rm -rf /var/www/html
  ```

- Create the app directory with the correct permissions

  ```bash
  sudo mkdir -p /app
  sudo chown -R nginx:nginx /app
  ```

- Copy the repo to the app directory

  ```bash
  sudo cp -r /path/to/cloned/repo /app
  ```

#### ENV setup

```bash
cp .env.example .env
```

Edit the variables inside .env to your specs.

#### Database setup

- Run the scritps/dev_db_init.sql script to create the database with example data

  ```bash
  mysql -u root -p < scripts/dev_db_init.sql
  ```

#### Install cronjob

A single job added to your crontab manages all the others in the system. The cron script is located in **crontab** directory. Example absolute path might look like **/app/crontab/cron.php**.

```bash
crontab -e
```

Then add:

```bash
* * * * * php /app/crontab/cron.php
```

#### Build

```bash
cd /app
composer install --optimize-autoloader --no-interaction --no-progress --no-ansi
```

#### Final steps

You should now restart NGINX:

```bash
sudo systemctl restart nginx
```

### Docker

#### Installation

[Install Docker v >= 20.10.21](https://docs.docker.com/engine/install/)

#### Build

```bash
docker build -t casper-portal-api .
```

#### Run

You can run the docker image specifying the environment variables in the .env.example file in this way:

```bash
docker run -e APP_NAME=[app_name] -e CORS_SITE=[cors_site] .. .. -p 80:80 casper-portal-api
```

Or you can create a .env file and run the docker image with the .env file:

```bash
docker run --env-file .env -p 80:80 casper-portal-api
```

## Test

There are unit tests, and integration tests built in, maintained to cover at least 80% of the entire codebase.

To run unit tests:

```bash
composer run-script test-unit
```

To run integration tests:

```bash
composer run-script test-integration
```

To run all tests:

```bash
composer run-script test
```

Generate documention using Phpdoc with:

```bash
composer run-script generate-docs
```

The last command creates the docs/ directory with static documentation html.

## CI

### GitHub Actions

There are two workflows in the .github/workflows directory:

- **dependecy_check.yml**. This workflow runs on every push and pull request. It checks for security vulnerabilities in the dependencies and reports them in PRs, failing on vulnerabilities of severity **high** or higher.

- **laravel.yml**. This workflow runs on every push and pull request. It runs unit, integration, and system tests. It generates coverage reports for SonarCloud.

### SonarCloud

SonarCloud is a code quality and security analysis tool. The SonarCloud page for this repo can be found [here](https://sonarcloud.io/summary/new_code?id=ledgerleapllc_CasperAssociationPortalBackend)

### Harness

Harness is a Continuous Delivery-as-a-Service (CDaaS) platform that simplifies the process of deploying, managing, and scaling applications. It provides a user-friendly interface and powerful features to manage deployments and reduce the risks associated with software delivery.

In this project, we are using Harness to deploy and manage a Kubernetes-based application. The provided files contain Kubernetes manifests and configuration files needed for deploying the application in different environments (staging, production, etc.). The files are organized as follows:

#### Files

##### .cicd/manifests/

This directory contains the Kubernetes manifests used for deploying the application.

- `config-map.yaml`: A ConfigMap containing the environment variables and application configuration data.
- `deployment.yaml`: A Deployment manifest that specifies the desired state of the application, including the number of replicas and container image.
- `ingress.yaml`: An Ingress manifest that configures the routing rules and TLS certificates for the application's external access.
- `namespace.yaml`: A Namespace manifest that defines the isolated environment where the application components will be deployed.
- `service.yaml`: A Service manifest that exposes the application internally within the Kubernetes cluster.

##### .cicd/values/

This directory contains the environment-specific values that are used to configure the application for different environments (staging, production, etc.).

- `prod.yaml`: Configuration values for the production environment.
- `staging.yaml`: Configuration values for the staging environment.

##### .cicd/values.yaml

This file contains the default configuration values that are shared across all environments. It also includes placeholders for Harness-specific variables, which will be replaced during the deployment process.

#### Harness and the provided files

Harness uses these files to deploy the application to different environments using a declarative approach. By providing environment-specific values, Harness can customize the deployment process and ensure that the application is correctly configured for each environment. The Kubernetes manifests define the desired state of the application, and Harness takes care of applying these manifests to the target Kubernetes cluster.

The Harness platform streamlines the deployment process, making it easy to manage and scale applications while reducing the risk of errors and downtime. The provided files are an integral part of this process, ensuring that the application is correctly deployed and configured in each environment.

### Pre-commit

#### Installation

```bash
pip install pre-commit
```

#### Usage

```bash
pre-commit install --hook-type pre-commit --hook-type pre-push
```

From now on, the pre-commit hooks will be run on every commit and push.

If you want to run the hooks manually, you can do so by running:

```bash
pre-commit run --all-files
```

#### Good to know

- If you want to skip the hooks, you can do so by adding the `--no-verify` flag to your commit or push command.
- If you want some markdownlint rules to be ignored, you can add them to the `.markdownlintignore` file. A PR would be appreciated if you find any rules that should be ignored.

Check out the [pre-commit](https://pre-commit.com/) tool for more information.

[This pre-commit configuration file](.pre-commit-config.yaml) contains a set of hooks that help to maintain code quality, enforce code standards, and automate formatting and validation processes for a variety of languages and tools. The hooks are organized into different sections based on their purpose.

#### pre-commit hooks

These hooks are run on every commit. They are used to validate and format code, and to detect security vulnerabilities.

If any of these hooks fail, the commit will be aborted, and the user will be prompted to fix the issues before committing again.

- **Semantic Commits**: Enforces conventional commit message patterns.

  - `conventional-pre-commit`

- **Docker**: Lints Dockerfiles using Hadolint with some specific rules ignored.

  - `hadolint`

- **NGINX**: Validates and formats NGINX config using a custom script.

  - `custom-nginx-validate-hook`

- **Python, JSON, YAML, and Generic**: Applies various pre-commit hooks from the official pre-commit-hooks repository for Python, JSON, YAML, and other generic code checks.

  - `check-ast`
  - `check-docstring-first`
  - `debug-statements`
  - `check-builtin-literals`
  - `double-quote-string-fixer`
  - `check-shebang-scripts-are-executable`
  - `check-symlinks`
  - `requirements-txt-fixer`
  - `check-added-large-files`
  - `check-case-conflict`
  - `check-merge-conflict`
  - `destroyed-symlinks`
  - `end-of-file-fixer`
  - `mixed-line-ending`
  - `trailing-whitespace`
  - `detect-aws-credentials`
  - `detect-private-key`
  - `check-json`
  - `check-yaml`

- **Security**: Detects AWS credentials and private keys in the codebase.

  - `detect-aws-credentials`
  - `detect-private-key`

- **Markdown**: Lints and auto-formats Markdown files using markdownlint and mdformat.

  - `markdownlint`
  - `mdformat`

- **Python - Security and Dependencies**: Checks Python dependencies for security vulnerabilities using the python-safety-dependencies-check hook.

  - `python-safety-dependencies-check`

- **Python - Lint and Autoformat**: Lints Python files with Flake8 and auto-formats them using Black.

  - `flake8`
  - `black`

- **GitHub Actions**: Validates GitHub workflows using check-jsonschema.

  - `check-github-workflows`

- **PHP**: Validates, lints, and runs static analysis on PHP code.

  - `php-lint-all`
  - `php-unit`
  - `php-cs`
  - `php-stan`

- **Kubernetes**: Validates and lints Kubernetes manifests using `kube-linter`.

- **HTML**: Validates HTML files and enforces specific rules.

  - `validate-html`
  - `forbid-html-img-without-alt-text`
  - `forbid-non-std-html-attributes`
  - `detect-missing-css-classes`

- **Various - Autoformat**: Auto-formats various file types using `prettier` with additional plugins for NGINX, properties, and shell files.

#### pre-push hooks

These hooks are run during the push stage of the git workflow. If any of these hooks fail, the push is rejected.

- **Docker Build**: Builds Docker images using a custom script during the push stage: `docker-build`

Please refer to the individual hook repositories and documentation for more information on the specific checks and formatting rules applied by each hook.
