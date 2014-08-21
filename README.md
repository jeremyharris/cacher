[![build
status](https://travis-ci.org/jeremyharris/cacher.svg?branch=master)](https://travis-ci.org/jeremyharris/cacher)

# Cacher

Cacher is a plugin for CakePHP that allows you to easily cache find results.
While most solutions for caching queries force you to overwrite `Model::find()`
in your AppModel, Cacher only requires adding a behavior to your model.

Have settings that hardly change? Have a database list of states or something
that never change but you still want them in the db? Just like caching your
results? Use Cacher!

## Requirements

* CakePHP >= 2.0.x (check tags for older versions of CakePHP)

## Usage

    var $actsAs = array(
        'Cacher.Cache'
    );

By default, Cacher uses the 'default' cache configuration in your core.php file.
If you want to use a different configuration, just pass it in the 'config' key.

    var $actsAs = array(
        'Cacher.Cache' => array(
            'config' => 'myCacheConfiguration'
        )
    );

> It's best to place Cacher last on your list of behaviors so the query Cacher
> looks for reflects the changes the previous behaviors might have made.

### Options that you can pass:

* `config` The name of an existing Cache configuration to duplicate (default 'default')
* `clearOnSave` Whether or not to delete the cache on saves (default `true`)
* `clearOnDelete` Whether or not to delete the cache on deletes (default `true`)
* `auto` Automatically cache (default `false`)
* `gzip` Automatically compress/decompress cached data (default `false`)

### Using Cacher with `Model::find()`, `Controller::paginate()`, etc.

If you set auto to false, you can pass a `'cacher'` key in your query that is
either `true` to cache the results, `false` to not cache it, or a valid
`strtotime()` string to set a duration for that specific call.

    // cache the results of this query for a day
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%awesome%'),
		  'cacher' => '+1 day'
    ));
    // don't cache the results of this query at all
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%lame%'),
		  'cacher' => false
    ));
    // cache using the default settings even if auto = false
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%okay i guess%'),
		  'cacher' => true
    ));

## How it works

Cacher intercepts any find query and temporarily changes the datasource to one 
that handle's checking the cache..

You can always disable Cacher by using `Behavior::detach()` or
`Behavior::disable()`.

## Features

* Quick and easy caching by just attaching the behavior to a model
* Clear cache for a specific model on the fly using `$this->Post->clearCache()`
* Clear a specific query by passing the conditions to `clearCache()`

## Todo

* I'd like to add other caching functionality to make it more all-in-one
* Would like to make the Cache datasource a reuseable, standalone datasource

## Notes

Since Cacher caches the entire results of a find, some cache can become stale
before it's parent does. For example, let's say you cache the results of finding 
a post and containing all comments. If a comment is deleted and the cache remains, 
it will show that comment. The only way to remove it would be to invalidate the
original query on the Post model. Ideas around this have been passed around
between some developers and I'm still trying to figure out the best way to handle
this.
