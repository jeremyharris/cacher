# Cacher

Cacher is a plugin for CakePHP that allows you to easily cache find results.
While most solutions for caching queries force you to overwrite `Model::find()`
in your AppModel, Cacher only requires adding a behavior to your model.

Have settings that hardly change? Have a database list of states or something
that never change but you still want them in the db? Just like caching your
results? Use Cacher!

## Usage

    var $actsAs = array(
        'Cacher.Cache'
    );

You can send any options you would normally use in `Cache::config()`. By default
Cacher caches results for 6 hours. You can change this by passing `duration`
with your duration as specified in `Cache::config()`. If you already have a
Cache configuration that you'd like to use:

    var $actsAs = array(
        'Cacher.Cache' => array(
            'config' => 'myCacheConfiguration'
        )
    );

### Options that you can pass:

* `config` The name of an existing Cache configuration to duplicate
* `clearOnSave` Whether or not to delete the cache on saves
* `clearOnDelete` Whether or not to delete the cache on deletes
* `auto` Automatically cache
* any options taken by `Cache::config()` will be used if `config` is not defined

### Using Cacher with `Model::find()`, `Controller::paginate()`, etc.

If you set auto to false, you can pass a `'cache'` key in your query that is
either `true` to cache the results, `false` to not cache it, or a valid
`strtotime()` string to set a duration for that specific call.

    // cache the results of this query for a day
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%awesome%'),
		  'cache' => '+1 day'
    ));
    // don't cache the results of this query at all
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%lame%'),
		  'cache' => false
    ));
    // cache using the default settings even if auto = false
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%okay i guess%'),
		  'cache' => true
    ));

## How it works

Cacher caches the query results under the cache configuration's path. The default
path is `CACHE.'cacher'`. It also differentiates between datasources, so a cache
file for your a Post model using your default datasource would store under
`app/tmp/cache/cacher/cacher_default_post_[hash]`.

It does this by intercepting any find query and changing the datasource to one
that handle's the database read. Your datasource is reset after the read is
complete.

You can always disable Cacher by using `Behavior::detach()` or
`Behavior::disable()`.

## Features

* Quick and easy caching by just attaching the behavior to a model
* Clear cache for a specific model on the fly using `$this->Post->clearCache()`
* Clear a specific query by passing the conditions to `clearCache()`

## Todo

* I'd like to add other caching functionality to make it more all-in-one
* Need to add tests that show it works with any DataSource