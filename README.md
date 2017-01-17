Spiral Core Components
================================

[![Latest Stable Version](https://poser.pugx.org/spiral/components/v/stable)](https://packagist.org/packages/spiral/components) 
[![Total Downloads](https://poser.pugx.org/spiral/components/downloads)](https://packagist.org/packages/spiral/components)
[![License](https://poser.pugx.org/spiral/components/license)](https://packagist.org/packages/spiral/components)
[![Build Status](https://travis-ci.org/spiral/components.svg?branch=master)](https://travis-ci.org/spiral/components)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/spiral/components/badges/quality-score.png)](https://scrutinizer-ci.com/g/spiral/components/?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/spiral/components/badge.svg?branch=feature/pre-split)](https://coveralls.io/github/spiral/components?branch=feature/pre-split)

<b>[Documentation](http://spiral-framework.com/guide)</b> | [Framework Bundle](https://github.com/spiral/spiral) | [Skeleton Application](https://github.com/spiral/application)

# Components included
  - Core interfaces and DI
  - Stempler template processor
  - Tokenizer, class locator, invocation locator
  - Debug, profiling and dump components
  - FileManager and Abstract Storage (Amazon, Rackspace, SFTP, FTP, GridFS)
  - Pagination
  - DBAL, schema introspection, comparation, scaffolding
  - Iehahrical ODM
  - ORM, schema scaffolding, eager loading, transactional active record, memory mapping
  - Security (NIST RBAC)
  - Code scaffolding

# Running Tests
Install component dependencies first, make sure you have proper .env file with details about
connected databases and storage component server configurations:

```
#Amazon storage
STORAGE_AMAZON_KEY =
STORAGE_AMAZON_SECRET = /
STORAGE_AMAZON_BUCKET =
STORAGE_AMAZON_PREFIX = amazon:

#FTP Storage
STORAGE_FTP_HOST = localhost
STORAGE_FTP_USERNAME = ftp
STORAGE_FTP_PASSWORD =
STORAGE_FTP_DIRECTORY = ftp-uploads/
STORAGE_FTP_PREFIX = ftp:

#SFTP storage
STORAGE_SFTP_HOST = 192.168.0.102
STORAGE_SFTP_USERNAME = sftp
STORAGE_SFTP_PASSWORD =
STORAGE_SFTP_HOME = /
STORAGE_SFTP_DIRECTORY = sftp-uploads/
STORAGE_SFTP_PREFIX = sftp:

#Rackspace
STORAGE_RACKSPACE_USERNAME =
STORAGE_RACKSPACE_API_KEY =
STORAGE_RACKSPACE_CONTAINER =
STORAGE_RACKSPACE_REGION = IAD
STORAGE_RACKSPACE_PREFIX = rackspace:

#Mongo
MONGO_CONNECTION = mongodb://localhost:27017
MONGO_DATABASE = phpunit
```

You can find sample env in `.env.sample`, DO NOT commit your .env into repository. To run tests
execute:

```
phpunit
```

## Verbose Testing
In order to enable additional profiling mechanisms in spiral tests set following variable in your 
env configuration:

```
PROFILING = true
```

This is enable echoing of Storage, Database and ORM component log messages.
![Profiling](http://image.prntscr.com/image/96e68443490948e59badf8907f8ee0fd.png)
