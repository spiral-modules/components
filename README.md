Spiral Core Components
================================

[![Build Status](https://travis-ci.org/spiral/components.svg?branch=master)](https://travis-ci.org/spiral/components)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/spiral/components/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/spiral/components/?branch=master)

This repository contains the primary Spiral components used within the Spiral framework bundle.

The components included are:
* Cache with contextual injections
* Debugger, Benchmarker and Global Loggers
* Encrypter and Randomizer
* Simple IoC Container and set of Core contracts (Singletons and Injectors)
* Memory interface
* DBAL with Database partinions, Query Builders and schema reflections (bidirectional): MySQL, PostgresSQL, SQLite, SQLServer
* ODM engine with inheritance, composition and aggregation
* Simple ORM, includes database scaffolding, relations (+polymorphic for fun), eager loading and easy joins
* Http Dispatcher at top of PSR7 with middlewares, Routing
* StorageManager with remote abstractions (Amazon, Rackspace, GridFS, FTP, SFTP) + PSR7 streams
* Templater engine with HTML based syntax, inheritance and widgets
* Tokenizer with FileReflections and class locator functionality
* Basic DataEntity models with Behaviour Schemas (foundation of ORM and ODM)
* Validation component + set of checkers
* Pagination component

Code style: PSR-2 (minor violations, feel free to fix), base line width is 100.

Unit Testing is a work in progress.

Read more with examples in [spiral guide](https://github.com/spiral/guide). Check [Framework Bundle](https://github.com/spiral/spiral). Framework interfaces [can be found here](https://github.com/spiral/guide/blob/master/framework/interfaces.md).

**P.S. Need help to identify what parts of framework can be swapped with Symfony, Zend components without breaking internal interfaces. So far best candidates: FilesInterface, Events\DispatcherInterface, TranslatorInterface (?).**
