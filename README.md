# USER DATA MANAGEMENT API
This Project involves building a set of APIs that manage user data, interact with a database, and handle email notifications. The APIs will handle operations such as uploading user data, viewing user data, backing up the database, and restoring the database.

## Table of Contents
- [Prerequisites](#prerequisites)
- [Setup](#setup)
  - [Clone the Repository](#clone-the-repository)
  - [Setup UserAPI](#setup-userapi)
- [Connect Database](#create-database)
- [Running The Application](#running-the-application)

## Prerequisites

Make sure you have the following installed on your machine:

- php
- symphony
- mysql
- composer

## Setup

### Clone the Repository
```bash
git clone https://github.com/dharamveer77/User-Data-Api.git
cd userapi #or change directory accordingly
```

### Setup UserAPI
```
composer require symfony/orm-pack
composer require symfony/mailer
composer require symfony/messenger #async mails
```

### Connect Database
in .env file connect your Mysql database and change username and password to Your configurations
DATABASE_URL="mysql://username:password@127.0.0.1:3306/Users"

```
php bin/console doctrine:database:create
php bin/console make:entity User
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Running The Application
```
php -S 127.0.0.1:8000 -t public  # or symfony serve
```

