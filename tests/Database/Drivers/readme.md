Any advices how to test thing like that? Should i create and drop database for each test? Should
i unify test first (schemas, queries) and then test it? I assume travis-ci must be configured with
databases to work well. 

Probably start with SQLite for now. Put database names into ENV? Relay on compilation tests for now?