CHANGELOG for 0.9.0 RC
======================

0.9.0 (23.01.2017)
-----
**General**
  * Dropped support of PHP5+
  * Code coverage improvements
  * Cache component removed (replaced with PSR-16)
  * Views abstractions removed
  * Monolog dependency removed
  * Validation component moved to Framework bundle
  * Transaction component moved to Framework bundle
  * Encryption component moved to Framework bundle
  * Migrations component moved in
    * Automatic migration generation is now part of Migration component
  * Security component moved in
  * PHPUnit updated to 5.0 branch
  * Symfony dependencies updated to 3.0 branch
  * Schema definitions moved to array constants instead of default property values
  * Simplified PaginatorInterface
  * Reactor component moved in

**DBAL** 
  * Improved polyfils for SQLServer
  * Improved SQL injection prevention
  * Refactoring of DBAL schemas
  * Bugfixes
    * Unions with with ordering in SQLServer
    * Invalid parameter handling for update queries with nested selection

**ORM**
  * Refactoring of SchemaBuilder
  * RecordSelector does not extend SeletQuery anymore
  * New Features
    * Transactional support
    * Improvment memory mapping
    * Improvement tree operations (save)
  * Removed features
    * ActiveRecord thought direct table communication
    * MutableNumber accessor
    * Validations
  * Bugfixes
    * Bugfix: ManyToMany relations to non saved records
    * BelongsToRelation to non saved records
    
**ODM**
   * Moved to latest PHP7 mongo drivers
   * Removed features
     * Validations
   * Removed parent document reference in compositions
   
**Storage**
   * Improved implementation of RackspaceServer
   * Added GridFS server support
