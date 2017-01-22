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
  - Core interfaces, autowiring DI, declarative singletons and auto injections
  - Stempler template composer (HTML syntax)
  - Tokenizer, class locator, invocation locator
  - Debug, Profiling and Dump components
  - FileManager and Abstract Storage (Amazon, Rackspace, SFTP, FTP, GridFS)
  - Pagination
  - DBAL, schema introspection, comparision, sql fallbacks, nested queries
  - ORM, schema scaffolding, eager, inner and lazy loading, transactional, memory mapping
  - Migrations (DBAL based), automatic migration scaffolding
  - Iehahrical ODM
  - Security layer (NIST RBAC)
  - Reactor, code scaffolding

# Running Tests
Install component dependencies first, make sure you have proper .env file with details about
connected databases and storage component server configurations, you can find sample env in `.env.sample`,
DO NOT commit your .env into repository. To run tests execute:

```
phpunit
```

## Verbose Testing
In order to enable additional profiling mechanisms in spiral tests set following variable in your 
env configuration:

```
PROFILING = true
```

This option will enable echoing of Storage, Database and ORM component log messages.
![Profiling](http://image.prntscr.com/image/539b6b6ae59a4aceaf86bf1747c994fb.png)
