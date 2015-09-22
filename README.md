Spiral Framework Core Components
================================

[![Build Status](https://travis-ci.org/spiral/components.svg?branch=master)](https://travis-ci.org/spiral/components)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/spiral/components/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/spiral/components/?branch=master)

This section contains the primary Spiral components used within the Spiral framework bundle.

The components included are:
* Cache
* Debugger, Benchmarker and Global Loggers
* Encrypter and Randomizer
* Simple IoC Container and set of Core contracts (Singletons and Injectors)
* Memory interface (&#1000;)
* DBAL with Database partinions, Query Builders and schema reflections (bidirectional): MySQL, PostgresSQL, SQLite, SQLServer
* ODM engine with inheritance, composition and aggregation
* Hybrid ORM (DataMapper + ActiveRecord) with the plans to become a cleaner data mapper (non breaking)
* Http Dispatcher at top of PSR7 with middlewares, Routing
* StorageManager with remote abstractions (Amazon, Rackspace, GridFS, FTP, SFTP)
* Templater engine with HTML based syntax, inheritance and widgets
* Tokenizer with FileReflections, Class locations functionality
* Basic DataEntity models with behaviour schemas (foundation of ORM and ODM)
* Validation component + set of checkers
* Pagination component

Unit Testing is a work in progress.

Read more with examples in [spiral guide](https://github.com/spiral/guide).

P.S. Beta release coming over next week or so once documentation is complete (09/23/2015).
