Spiral Framework Core Components
================================

[![Build Status](https://travis-ci.org/spiral/components.svg?branch=master)](https://travis-ci.org/spiral/components)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/spiral/components/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/spiral/components/?branch=master)

Contains primary spiral components used by spiral framework bundle.

Components included:
* Cache
* Debug and Global Loggers
* Encrypter and Randomizer
* Container and set of Core contracts (Singletons and Injectors)
* Memory interface
* DBAL with Database partitions, Query builders and schema reflections (bidirectional) - MySQL, PostgresSQL, SQLite, SQLServer
* ODM engine with inheritance, composition and aggregation
* Hybrid ORM (DataMapper + ActiveRecord) with potentical to become more clean data mapper
* Http Dispatcher at top of PSR7 with middlewares and rounting
* StorageManager with remote abstractions (Amazon, Rackspace, GridFs, FTP, SFTP)
* Templater engine with HTML based syntax, inheritance and widgets
* Tokenizer with FileReflections and class locators
* Basic DataEntity models with behaviour schemas (foundation of ORM and ODM)
* Validation component + set of checkers
* Pagiantion component

Unit Testing is in process.
