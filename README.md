# Migrating to Islandora CLAW using CSVs

## Introduction

Storing metadata as rows in a CSV file is a great way to get migrated into CLAW.  CSVs are easy to understand and work with, and there's good tooling available for using them to migrate.  The [migrate_source_csv](https://www.drupal.org/project/migrate_source_csv) contrib module provides a source plugin that's compatible with Drupal 8's [Migrate API](https://www.drupal.org/docs/8/api/migrate-api/migrate-api-overview). And by using the [migrate_plus](https://www.drupal.org/project/migrate_plus/) module, you can model customized migrations using yml and package them up as [features](https://www.drupal.org/project/features/).

In fact, this module is one such feature.  It even contains a `data` directory filled with some sample images and a CSV full of metadata.  In this README, we'll be inspecting each migration file in detail before running it .  You'll start out by migrating the images themselves first, and then you'll create various Drupal entities to describe the files from the metadata in the CSV.  It's not as scary as it sounds, but you will need a few things before beginning:

1. An instance of Islandora CLAW.  Use [CLAW playbook](https://github.com/Islandora-Devops/claw-playbook) to spin up an environment pre-loaded with all the modules you need (except this one)
1. Some basic command line skills.  You won't need to know much, but you'll have to `vagrant ssh` into the box, navigate into Drupal, and use `git` and `drush`, etc...  If you can copy/paste into a terminal, you'll survive.

A big part of this tutorial relies on the [islandora_demo](https://github.com/Islandora-CLAW/islandora_demo) and [controlled_access_terms_default_configuration](https://github.com/Islandora-CLAW/controlled_access_terms/tree/8.x-1.x/modules/controlled_access_terms_default_configuration) features, which define the default metadata profile for Islandora (which we'll be migrating into).  You're not required to use the `islandora_demo` or `controlled_access_terms_default_configuration` for your repository, but for the purposes of demonstration, it saves you a lot of UI administrivia so you can focus just on the learning how to migrate.  By the time you are done with this exercise, you'll be able to easily apply your knowledge to migrate using any custom metadata profile you can build using Drupal. 

## Overview

In Islandora, migrations involve creating several different types of content entities in Drupal to represent a single item in a repository.  Each row in the CSV must contain enough information to create
- a file, which holds the actual binary contents of an item
- a node, which holds the descriptive metadata for an item
- a media, which holds technical metadata and references the file and the node, linking the two together

However, buried in your descriptive metadata are often references to other entities which aren't repostiory items themselves, but records still need to be kept for them.  Authors, publishers, universities, places, etc... are all their own entities, and are referenced by other entities.  So there's the potential to have a lot of different entity types described in a single row in a CSV.

In this tutorial, we're working wth `islandora_demo` and `controlled_access_terms` entities and will be migrating 5 entity types in total.
- file
- node
- media
- subject
- person

We'll do this by creating three migrations, which follow the [Extract-Transform-Load pattern](https://en.wikipedia.org/wiki/Extract,_transform,_load).  You extract the information from a source, process the data to transform it into the format you need, and load it into the destination system (e.g. Drupal).  Migrations are stored in Drupal as configuration, which means they can be represented in yml, transferred to and from different sites, and are compatible with Drupal's configuration synchronization tools. And the structure of each yml file is arranged to follow the Extract-Transform-Load pattern.

Now we're migrating five entity types, but we're only writing three migrations: files, nodes, and media.  The other two, subjects and agents, will be generated during the node migration.  This will give us a chance to show off some techniques for working with multi-valued fields, entity reference fields, and complex field types like `controlled_access_terms`'s `typed_relation` field.  We'll also see how the migrate framework can help de-duplicate, and at the same time, linked data-ize :tm: your data by looking up previously migrated entities.  So hold on to your hats.  First, let's get this puppy onto your Islandora instance.

## Installation

From your `claw-playbook` directory, issue the following commands to clone down this module from git:
- `vagrant ssh` to shell into your Islandora instance.
- `cd /var/www/html/drupal/web/modules/contrib` to get to your modules directory.
- `git clone https://github.com/dannylamb/migrate_islandora_csv` to clone down the repository from github.
- `drush en migrate_islandora_csv` to enable the module, installing the migrations as configuration.

Now lets go migrate some files.

## Ingesting Files

To ingest files from CSV, you need a column containing paths to the files you wish to ingest.  These files need to be accessible from the server that's running Drupal so that the migrate framework can find them.  This tutorial assumes you're working with the sample images provided in the module, which will be located at `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images`.

Open up the csv file at `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/migration.csv`, and you'll see a `file` column in there populated with paths to the sample images.

|file|
|----|
|/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images/Nails Nails Nails.jpg|
|/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images/Free Smells.jpg|
|/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images/Nothing to See Here.jpg|
|/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images/Call For Champagne.jpg|
|/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images/This Must Be The Place.jpg|


Open up the files migration at `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/config/install/migrate_plus.migration.file.yml`.  You'll see the following migration config:

```yml
id: file
label: Import Image Files
migration_group: migrate_islandora_csv 

source:
  plugin: csv
  path: '/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/migration.csv'
  delimiter: ','

  # 1 means you have a header row, 0 means you don't
  header_row_count: 1 

  # Each migration needs a unique key per row in the csv.  Here we're using the file path.
  keys: 
    - file 

  # You can't enter string literals into a process plugin, but you can give it a constant as a 'source'.
  constants:
    # Islandora uses flysystem and stream wrappers to work with files.  What we're really saying here is
    # to put these files in Fedora in a 'csv_migration' folder.  It doesn't matter if the directory
    # doesn't exist yet, it will get created for you automatically.
    destination_dir: 'fedora://csv_migration' 

process:

  ##
  # The following two fields are temporary, and just used to generate a destination for the file.
  ##

  # Hack the file name out of the full path provided in the 'file' column.
  filename:
    -
      plugin: callback
      callable: pathinfo
      source: file
    -
      plugin: extract
      index:
        - basename

  # Construct the destination URI using the file name.
  destination:
    plugin: concat
    delimiter: /
    source:
      - constants/destination_dir
      - '@filename'

  ##
  # Here's where we copy the file over and set the uri of the file entity.
  ##
  uri:
    plugin: file_copy
    source:
      - file # The source column in the CSV
      - '@destination' # The destination entry from above  

destination:
  # These are Drupal 'image' entities we're making, not just plain 'file' entities.
  plugin: 'entity:file'
  type: image
```

### Anatomy of a Migration

It seems like a lot to take in at first, but there's a pattern to Drupal migrations.  They always contain three key sections: `source`, `process`, and `destination`.  And these sections correspond exactly to Extract, Transform, and Load.  

#### Source

The `source` section contains the configuration needed to create a Drupal source plugin that will extract the data.  A source plugin provides "rows" of data to processing plugins so that they can be worked on.  In this case, we're using the `csv` source plugin, which very literally uses rows, however you can have source plugins that work with other data formats like XML and JSON. Look at the config from this section.
```yml
source:
  plugin: csv
  path: '/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/migration.csv'
  delimiter: ','
  header_row_count: 1
  keys: 
    - file 
  constants:
    destination_dir: 'fedora://csv_migration' 
```
You can see we provide a path to its location, what delimiter to use, if it uses a header row, and which column contains a unique key for each entry.  Constants can also be defined in the `source` section (more on those later).

#### Process

The `process` section contains entries for a series of processing steps to transform the source data.  Each step has a name and contains the configuration for one or more process plugins.  Multiples plugins are executed in sequence, with the results getting passed from one to another, forming a pipeline.  In this fashion,  you can transform data from the CSV into a format that Drupal is expecting.  There are many process plugins available, and we'll cover several throughout this tutorial.

For each row of the CSV, each of these steps will be executed.  If the name of a step happens to be the same as a field or property name, the migrated entity will have that value for that field or property.  This is how you can apply metadata from the CSV to an entity.  If it's not named after a field or property, the migrate framework assumes it's a temporary value you're using as part of more complex logic.  It won't wind up on the entity when the migration is done, but it will be available for you to use within other process plugins.  You can always spot a temporary value by the fact that it's prefixed with an `@`.  You can also pass constants into process plugins, which are prefixed with `constants/`.

#### Destination

The `destination` section contains the configuration that describes what gets loaded into Drupal.
```yml
destination:
  plugin: 'entity:file'
  type: image
```
You can create any type of content entity in Drupal. In this case, we're making file entities.  Specifically, we're making images, which are a special type of file entity.

#### The Process Section in Depth

In the `process` section of the migration, we're copying the images over into a Drupal file system and setting the `uri` property on the corresponding File entity.
```yml
  uri:
    plugin: file_copy
    source:
      - file
      - '@destination'  
```
To do this, we're using the `file_copy` process plugin.  But to use it, we have to know where a file is located and where we it want it copied to.  We know where the file resides, we have that in the CSV's `file` column.  But we're going to have to do some string manipuation in order to generate the new location where we want the file copied. We're trying to convert something like `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images/Free Smells.jpg` to `fedora://csv_migration/Free Smells.jpg`.

The uri we're constructing is a stream wrapper of the form `scheme://path/to/file`.  Islandora uses `flysystem`, which allows for integration with many different types of filesystems, both remote and local.  With `flysystem`, the scheme part of the uri is the name of a filesystem.  By default, Fedora is exposed using the scheme `fedora://`.  So by setting uri to `fedora://csv_migration/Free Smells.jpg`, we're saying "put Free Smells.jpg in the csv_migration directory in Fedora."

Now, to perform this string manipulation in PHP, we'd do something like

```php
$info = pathinfo($filepath);
$filename = $info['basename'];
$destination = "fedora://csv_migration/" . $filename;
```

Which we will mimic exactly in the `process` section of our migration config.  Just like we declare variables and call functions with PHP code, we can make entries in the `process` section to store the output of Drupal process plugins. We'll build up a `destination` 'variable' and pass it into the `file_copy` process plugin.  

To start, we'll get the filename using two process plugins:
```yml
  filename:
    -
      plugin: callback
      callable: pathinfo
      source: file
    -
      plugin: extract
      index:
        - basename
```
The first process plugin, `callback`, lets you execute any PHP function that takes a single input and returns an output.  It's not as flexible as making your own custom process plugin, but it's still pretty useful in a lot of situations.  Here we're using it to call `pathinfo()`, telling it to use the `file` column in the CSV as input.  We pass the resulting array from `pathinfo()` to the `extract` process plugin, which pulls data out of arrays using the keys you provide it under `index`.

Now that we have the file name, we have to prepend it with `fedora://csv_migration/` to make the destination uri.  In our PHP code above, we used `.` to concatenate the strings.  In the migration framework, we use the `concat` process plugin.  You provide it with two or more strings to concatenate, as well as a delimiter.

```yml
  destination:
    plugin: concat
    delimiter: /
    source:
      - constants/destination_dir
      - '@filename'
```

In our PHP code, we concatenated the `$filename` variable with a string literal. In our process plugin, we can provide the variable, e.g. the output of the `filename` process step, by prefixing it with an `@`.  We can't, however, pass in `fedora://csv_migration` directly as a string.  At first glance, you might think something like this would work, but it totally doesn't:
```yml
  # Can't do this.  Won't work at all.
  destination:
    plugin: concat
    delimiter: /
    source:
      - 'fedora://csv_migration'
      - '@filename'
```
That's because the migrate framework only interprets `source` values as names of columns from the csv or names of other process steps.  Even if they're wrapped in quotes.  It will never try to use the string directly as a value.  To circumvent this, we decare a constant in the `source` section of the migration config.

```yml
  constants:
    destination_dir: 'fedora://csv_migration'
```

This constant can be referenced as `constants/destination_dir` and passed into the concat process plugin as a `source`.

### Running the File Migration

Migrations can be executed via `drush` using the `migrate:import` command.  You specify which migration to run by using the id defined in its yml.  To run the file migration from the command line, make sure you're within `/var/www/html/drupal/web` (or any subdirectory) and enter
```bash
drush migrate:import file
```
If you've already run the migration before, but want to re-run it for any reason, use the `--update` flag.
```bash
drush migrate:import file --update
```
You may have noticed that migrations can be grouped, and that they define a `migration_group` in their configuration.  You can execute an entire group of migrations using the `--group` flag.  For example, to run the entire group defined in this module
```bash
drush migrate:import --group migrate_islandora_csv
```
You can also use the `migrate:rollback` command to delete all migrated entities.  Like `migrate:import`, it also respects the `--group` flag.  So to rollback everything we just did:
```bash
drush migrate:rollback --group migrate_islandora_csv
```
If something goes bad during development, sometimes migrations can get stuck in a bad state.  Use the `migrate:reset` command to put a migration back to `Idle`.  For example, with the `file` migration, use
```bash
drush migrate:reset file
```

Make sure you've run (and not rolled back) the `file` migration.  It should tell you that it successfully created 5 files.  You can confirm its success by visiting http://localhost:8000/admin/content/files.  You should see 5 images of neon signs in the list.

## Ingesting Nodes

Those five images are nice, but we need something to hold their descriptive metadata and show them off.  We use nodes in Drupal to do this, and that means we have another migration file to work with.  Nestled in with our nodes' descriptive metadata, though, are more Drupal entities, and we're going to generate them on the fly while we're making nodes.  While we're doing it, we'll see how to use pipe delimited strings for multiple values as well as how to handle `typed_relation` fields that are provided by `controlled_access_terms`. Open up `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/config/install/migrate_plus.migration.node.yml` and check it out.

```yml
# Uninstall this config when the feature is uninstalled
dependencies:
  enforced:
    module:
      - migrate_islandora_csv

id: node 
label: Import Nodes from CSV 
migration_group: migrate_islandora_csv

# Pull from a CSV, and use the 'file' column as an index
source:
  plugin: csv
  path: modules/contrib/migrate_islandora_csv/data/migration.csv
  header_row_count: 1
  keys:
    - file 
  constants:
    model: Image 
    relator: 'relators:pht' 

# Set fields using values from the CSV
process:
  title: title

  # We use the skip_on_empty plugin because
  # not every row in the CSV has subtitle filled
  # in.
  field_alternative_title:
    plugin: skip_on_empty
    source: subtitle 
    method: process

  field_description: description

  # Dates are EDTF strings
  field_edtf_date: issued
    
  # Make the object an 'Image'
  field_model:
    plugin: entity_lookup
    source: constants/model
    entity_type: taxonomy_term
    value_key: name 
    bundle_key: vid
    bundle: islandora_models 

  # Split up our pipe-delimited string of
  # subjects, and generate terms for each.
  field_subject:
    -
      plugin: skip_on_empty
      source: subject 
      method: process
    -
      plugin: explode
      delimiter: '|'
    -
      plugin: entity_generate
      entity_type: taxonomy_term
      value_key: name
      bundle_key: vid
      bundle: subject

  # Complex fields can have their individual
  # parts set independently.  Use / to denote
  # you're working with a proerty of a field
  # directly.
  field_linked_agent/target_id:
    plugin: entity_generate
    source: photographer 
    entity_type: taxonomy_term
    value_key: name
    bundle_key: vid
    bundle: person 

  # Hard-code the rel_type to photographer
  # for all the names in the photographer
  # column.
  field_linked_agent/rel_type: constants/relator

# We're making nodes
destination:
  plugin: 'entity:node'
  default_bundle: islandora_object
```

The `source` section looks mostly the same other than some different constants we're defining.  If you look at the `process` section, you can see we're taking the `title`, `description`, and `issued` columns from the CSV and applying them directly to the migrated nodes without any manipulation.
```yml
  title: title
  field_description: description
  field_edtf_date: issued
```
For `subtitle`, we're passing it through the `skip_on_empty` process plugin because not every row in our CSV has a subtitle entry.  It's very useful when you have spotty data, and you'll end up using it a lot.  The `method: process` bit tells the migrate framework only skip that particular field if the value is empty, and not to skip the whole row.  It's important, so don't forget it.  The full yml for setting `field_alternative_title` from subtitle looks like this:
```yml
  field_alternative_title:
    plugin: skip_on_empty
    source: subtitle 
    method: process
```
Now here's where things get interesting.  We can look up other entities to populate entity reference felds.  For example, all Repository Items have an entity reference field that holds a taxonomy term from the `islandora_models` vocabulary.  All of our examples are images, so we'll look up the Image model in the vocabulary since it already exists (it gets made for you when you use claw-playbook).  We use the `entity_lookup` process plugin to do this.
```yml
  field_model:
    plugin: entity_lookup
    source: constants/model
    entity_type: taxonomy_term
    value_key: name 
    bundle_key: vid
    bundle: islandora_models
```
The `entity_lookup` process plugin looks up an entity based on the configuration you give it.  You use the `entity_type`, `bundle_key`, and `bundle` configurations to limit which entities you search through.  `entity_type` is, as you'd suspect, th type of entity: node, media, file, taxonomy_term, etc...  `bundle_key` tells the migrate framework which property holds the bundle of the entity, and `bundle` is the actual bundle id you want to restrict by.  The search value you're looking for is the `source` configuration.  In this case we're looking for the string "Image", which we've defned as a constant.  And we're comparing it to the `name` field on each term by setting the `value_key` config.

If you're not sure that the entities you're looking up already exist, you can use the `entity_generate` plugin, which takes the same config, but will create a new entity if the lookup fails.  We use this plugin to create `subject` taxonomy terms that we tag our nodes with.  A node can have multiple subjects, so we've encoded them in the CSV as pipe delimited strings.

|subject|
|----|
|Neon signs\|Night|
|Neon signs\|Night\|Funny|
|Neon signs\|Night|
|Drinking\|Neon signs|
|Neon signs|

We can hack those apart easily enough.  In PHP we'd do something like
```php
$subjects = explode($string, '|');
$terms = [];
foreach ($subjects as $name) {
    $terms[] = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->create([
        ...
        'vid' => 'subject',
        'name' => $name,
        ...
    ]);
}
$node->set('field_subject', $terms);
```

With process plugins, that logic looks like
```yml
field_subject:
    -
      plugin: skip_on_empty
      source: subject 
      method: process
    -
      plugin: explode
      delimiter: '|'
    -
      plugin: entity_generate
      entity_type: taxonomy_term
      value_key: name
      bundle_key: vid
      bundle: subject
```
Here we've got a small pipeline that uses the `skip_on_empty` process plugin, which we've already seen, followed by `explode`.  The `explode` process plugin operates exactly like its PHP counterpart, taking an array and a delimiter as input.  The combination of `skip_on_empty` and `explode` behave like a foreach loop on the explode results.  If we have an empty string, nothing happens.  If there's one or more pipe delimited subject names in the string, then `entity_generate` gets called for each name that's found.  The `entity_generate` process plugin will try to look up a subject by name, and if that fails, it creates one using the name and saves a reference to it in the node.  So `entity_generate` is actually smarter than our pseudo-code above, because it can be run over and over again and it won't duplicate entities :champagne:

### Complex Fields

Some fields don't hold just a single type of value.  In other words, not everything is just text, numbers, or references.  Using the Typed Data API, fields can hold bags of named values with different types.  Consider a field that holds an RGB color.  You could set it with PHP like so:
```php
$node->set('field_color', ['R' => 255, 'G' => 255, 'B' => 255]);
```

You could even  have a multi-valued color field, and do something like this
```php
$node->set('field_color', [
  ['R' => 0, 'G' => 0, 'B' => 0],
  ['R' => 255, 'G' => 255, 'B' => 255],
]);
```

In the migrate framework, you have two options for handling these types of fields.  You can build up the full array they're expecting, which is difficult and often impossible to do without writing a custom process plugin. Or you set each named value in the field with separate process pipelines.

In `controlled_access_terms`, we have a notion of a `typed_relation`, which is an entity reference coupled with a marc relator.  It expects an associative array that looks like this:
```php
[ 'target_id' => 1, 'rel_type' => 'relators:ctb']
```

The `target_id` portion takes an entity id, and rel_type takes the predicate for the marc relator we want to use to describe the relationship the entity has with the repository item.  This example would reference taxonomy_term 1 and give it the relator for "Contributor".

If we want to set those values in yml, we can access `target_id` and `rel_type` independently by accessing them with a `/`.  
```yml
  field_linked_agent/target_id:
    plugin: entity_generate
    source: photographer 
    entity_type: taxonomy_term
    value_key: name
    bundle_key: vid
    bundle: person 

  field_linked_agent/rel_type: constants/relator
```

Here we're looking at the `photographer` column in the CSV, which contains the names of the photographers that captured these images.  Since we know these are photographers, and not publishers or editors, we can bake in the `relator` constant we set to `relators:pht` in the `source` section of the migration.  So all that's left to do is to set the taxonomy term's id via `entity_generate`.  If the lookup succeeds, the id is returned.  If it fails, a term is created and its id is returned.  In the end, by using the `/` syntax to set properties on complex fields, everything gets wrapped up into that nice associative array structure for you automatically.  Now let's run that migration.

### Running the node migration

Like with the file migration
```bash
drush migrate:import node
```
from anywhere within Drupal will fire off the migration.  Go to http://localhost:8000/admin/content and you should see five new nodes.  Click on one, though, and you'll see it's just a stub with metadata.  The csv metadata is there, links to other entities like subjects and photographers are there, but there's no trace of the corresponding files.  Here's where media entities come into play.

## Migrating Media

Media entities are Drupal's solution for fieldable files.  Since you can't put fields on a file, what you can do is wrap the file with a Media entity.  In addition to a file reference, technical and structural metadata for the file go on the Media entity.  For example, mimetype, file size, resolution, etc... all belong on a Media entity.  Media also have a few special fields that are required for Islandora, `field_media_of` and `field_use`, which denote what node owns the media and what role the media serves, repectively.  Since the Media entity references both the file it wraps and the node that owns it, Media entities act as a bridge between files and nodes, tying them together.  And to do this, we make use of one last process plugin, `migration_lookup`.  Open up `/var/www/html/drupal/web/modules/contrib/




