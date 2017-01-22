CHANGELOG for 0.9.0 RC
======================

0.9.0 (23.01.2017)
-----
**General**
  * Dropped support of PHP5+
  * Code coverage improvements
  * Cache component removed (replaced with PSR-16)
  * Views component moved to Framework bundle
  * Validation component moved to Framework bundle
  * Transaction component moved to Framework bundle
  * Encryption component moved to Framework bundle
  * Migrations component moved in
    * Automatic migration generation is now part of Migration component
  * Security component moved in
  * Monolog dependency removed
  * PHPUnit updated to 5.0 branch
  * Symfony dependencies updated to 3.0 branch
  * Schema definitions moved to array constants instead of default property values
  * Simplified PaginatorInterface
  * Reactor component moved in
  * Debugger (log manager) component removed 
  * Improved implementation of Tokenizer component

**Core**
  * ScoperInterface moved into Framework bundle
  * Container now validates scalar agument types when supplied by user

**DBAL** 
  * Improved polyfills for SQLServer
  * Improved SQL injection prevention
  * Improved timezone management
  * Refactoring of DBAL schemas
  * Bugfixes
    * Unions with ordering in SQLServer
    * Invalid parameter handling for update queries with nested selection

**Models**
  * Removed features
    * Embedded validations
    * Magic getter and setter methods
  * setValue and packValue methods added
  * "fields" property is now private
  * SolidableTrait is now part of models

**ORM**
  * Refactoring of SchemaBuilder
  * RecordSelector does not extend SeletQuery anymore
  * Transactional (UnitOfWork) support
  * Improvment memory mapping
  * Improvement tree operations (save)
  * Removed features
    * ActiveRecord thought direct table communication
    * MutableNumber accessor
    * Validations
  * Bugfixes
    * Bugfix: ManyToMany relations to non-saved records
    * BelongsToRelation to non-saved records
  * Definition of morphed relations must be explicit now
  * All ORM entities MUST have proper primary key now
  * Ability to define custom column types in combination with accessors
    
**ODM**
   * Moved to latest PHP7 mongo drivers
   * Removed features
     * Validations
   * Removed parent document reference in compositions
   * Scalar array split into multiple versions
   * CompositableInterface improved
   
**Storage**
   * Improved implementation of RackspaceServer
   * Added GridFS server support
