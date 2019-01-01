# Migrating to Islandora CLAW using CSVs

## Table of Contents

* [Summary](#summary)
* [Introduction](#introduction)
* [Dependencies](#dependencies)
* [Overview](#overview)
* [Ingesting Files](#ingesting-files)
    * [Anatomy of a migration](#anatomy-of-a-migration)
        * [Source](#source)
        * [Process](#process)
        * [Destination](#destination)
        * [The Process Section in Depth](#the-process-section-in-depth)
    * [Running the File Migration](#Running-the-file-migration)
* [Ingesting Nodes](#ingesting-nodes)
    * [Complex Fields](#complex-fields)
    * [Running the node migration](#running-the-node-migration)
* [Migrating Media](#migrating-media)
* [What have we learned](#what-have-we-learned)
* [Where to go from here](#where-to-go-from-here)


## Summary

#fixme saying what this module contains.
This repository, __migrate_islandora_csv__, is a tutorial (this README) that will introduce you to using the Drupal 8 Migrate module to create Islandora content in CLAW. It also contains an example Feature module and a convenient data folder to walk you through the process of migrating Islandora objects into CLAW using a CSV. 

When you enable this module, it will create three example migrations ready for you to use with the Migrate module. Each migration comes from one of the files in the `config/install` folder of this module. The `data` folder is a convenience so that the accompanying files are easily available on the Drupal server. 

When you are ready to create your actual migration, you can create a module from scratch patterned off this one, and use a similar method to test and iterate your migration.

## Introduction

__Why CSV?__ CSV files #fixme(TSV?) are easy to understand and work with, and there's good tooling available for using them with Drupal 8's [Migrate API](https://www.drupal.org/docs/8/api/migrate-api/migrate-api-overview), including:
* [migrate_source_csv](https://www.drupal.org/project/migrate_source_csv) Drupal contrib module provides a source plugin that reads from a CSV file, and
* [migrate_plus](https://www.drupal.org/project/migrate_plus/), a Drupal contrib module that provides more tools and flexibility for creating migrations, including the ability to create customized migrations using yml and package them up as [Features](https://www.drupal.org/project/features/).

In fact, this module is one such feature.  It even contains a `data` directory filled with some sample images and a CSV full of metadata.  In this README, we'll be inspecting each migration file in detail before running it .  You'll start out by migrating the images themselves first, and then you'll create various Drupal entities to describe the files from the metadata in the CSV.  It's not as scary as it sounds, but you will need a few things before beginning:

1. An instance of Islandora CLAW.  Use [CLAW playbook](https://github.com/Islandora-Devops/claw-playbook) to spin up an environment pre-loaded with all the modules you need (except this one)
1. Some basic command line skills.  You won't need to know much, but you'll have to `vagrant ssh` into the box, navigate into Drupal, and use `git` and `drush`, etc...  If you can copy/paste into a terminal, you'll survive.

A big part of this tutorial relies on the [islandora_demo](https://github.com/Islandora-CLAW/islandora_demo) and [controlled_access_terms_default_configuration](https://github.com/Islandora-CLAW/controlled_access_terms/tree/8.x-1.x/modules/controlled_access_terms_default_configuration) features, which define the default metadata profile for Islandora (which we'll be migrating into).  You're not required to use the `islandora_demo` or `controlled_access_terms_default_configuration` for your repository, but for the purposes of demonstration, it saves you a lot of UI administrivia so you can focus just on the learning how to migrate.  By the time you are done with this exercise, you'll be able to easily apply your knowledge to migrate using any custom metadata profile you can build using Drupal. 

## Dependencies

* [Migrate API](https://www.drupal.org/docs/8/api/migrate-api/migrate-api-overview)
* [migrate_source_csv](https://www.drupal.org/project/migrate_source_csv)
* [features](https://www.drupal.org/project/features/)



## Overview

The Migrate API is the main way to ingest batches of data into Drupal (and because CLAW is Drupal, into Islandora). The Migrate module only provides the framework, it's up to you to create the rules that take data from a "source" into a "mapping". Each set of these configurations is called a "migration". 

(#fixme this is lies but i'm not sure why) A migration can only create Entities of one type - whether that's taxonomy terms, nodes, files, etc. Since an Islandora Object in CLAW is made up of several different Drupal entities that refer to each other, it's going to take multiple migrations to create an Islandora object, and it's important to perform these migrations in a sensible order. 

A basic Islandora object is at minimum:
- a file, which holds the actual binary contents of an item
- a node, which holds the descriptive metadata for an item
- a media, which holds technical metadata and references the file and the node, linking the two together.

Therefore, each row in your CSV must contain enough information to create these. 

However, buried in your descriptive metadata are often references to other things which aren't repostiory items themselves, but records still need to be kept for them.  Authors, publishers, universities, places, etc... can be modelled as Drupal Entities, so that they can be referenced by other Entities.  So there's the potential to have a lot of different entity types described in a single row in a CSV.

In this tutorial, we're working with `islandora_demo` and `controlled_access_terms` entities, and will be migrating five entity types using the three migrations included in this module.
- file
- node
- media
- subject
- person

Migrations follow the [Extract-Transform-Load pattern](https://en.wikipedia.org/wiki/Extract,_transform,_load).  You extract the information from a source, process the data to transform it into the format you need, and load it into the destination system (e.g. Drupal).  Migrations are stored in Drupal as configuration, which means they can be represented in yml, transferred to and from different sites, and are compatible with Drupal's configuration synchronization tools. And the structure of each yml file is arranged to follow the Extract-Transform-Load pattern.

Now we're migrating five entity types, but we're only writing three migrations: files, nodes, and media.  The other two, subjects and agents, will be generated during the node migration.  This will give us a chance to show off some techniques for working with multi-valued fields, entity reference fields, and complex field types like `controlled_access_terms`'s `typed_relation` field.  We'll also see how the migrate framework can help de-duplicate, and at the same time, linked data-ize :tm: your data by looking up previously migrated entities.  So hold on to your hats.  First, let's get this puppy onto your Islandora instance.

To perform the migrations, we'll be using `drush`. We will be able to run each of the file, node, and media migrations seprarately or all at once in a group. We will also learn how to roll back a migration in case it didn't go as planned.

## Installation

From your `claw-playbook` directory, issue the following commands to enable this module:
- `vagrant ssh` to open a shell in your Islandora instance.
- `cd /var/www/html/drupal/web/modules/contrib` to get to your modules directory.
- `git clone https://github.com/dannylamb/migrate_islandora_csv` to clone down the repository from github.
- `drush en -y migrate_islandora_csv` to enable the module, installing the migrations as configuration.

Flush cache, then the migrations are visible through the GUI at Manage > Structure > Migrations (http://localhost:8000/admin/structure/migrate/manage/migrate_islandora_csv/migrations)

Now lets go migrate some files.

But first a cautionary note: as you saw, you can still `git clone` into the modules directory, but if you're installing a custom module that's intended to stay installed for the long term (unlike a migration feature, which you should probably uninstall and delete when you're done with it) then you may want to check with your devops folks and use Composer instead. However, using Git directly allows you to be more flexible when iterating and testing.


## Ingesting Files

To ingest files (i.e. just the raw binaries) from a CSV, you need:
* a column in the CSV containing paths to the files you wish to ingest, and
* the files need to be accessible from the server that's running Drupal so that the Migrate framework can find them.  

This tutorial assumes you're working with the sample images provided in the module, which will be located at `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/images`. When you're migrating for real, the files will have to be uploaded or otherwise made accessible to the server before this point. 

Open (#fixme like in Vim? do i actually need to edit these, or just look??) up the csv file at `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/data/migration.csv`, and you'll see a `file` column containing paths to the sample images.

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

The `source` section contains the information needed to configure a Drupal source plugin that will extract the data.  A source plugin provides "rows" of data to processing plugins so that they can be worked on.  In this case, we're using the `csv` source plugin, which very literally uses rows, however you can have source plugins that work with other data formats like XML and JSON. Look at the config from this section.
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
You can see we provide a path to its location, what delimiter to use, if it uses a header row, and which column contains a unique key for each entry.  Constants can also be defined here (more on those later).

#### Process (at a glance)

The `process` section is where we extract the desired bits from that row, transform them as desired, and populate them into an associative array. This section is a series of named steps, that call one or more process plugins. These plugins are executed in sequence, with the results getting passed from one to the next, forming a pipeline. By the end of the step, you have transformed the data (perhaps through text manipulation, concatenation, etc.) into the form that Drupal is expecting. The resulting value gets associated with the name of the step. 

If the name of a step is the same as a field or property name on the target entity, the migrated entity will have that value for that field or property.  This is how you can apply metadata from the CSV to an entity.  If the step name is not the name of a field or property on the target entity, the migrate framework assumes it's a temporary value you're using as part of more complex logic.  It won't wind up on the entity when the migration is done, but it will be available for you to use within other process plugins.  You can always spot when a temporary value is being used by the fact that it's prefixed with an `@`.  You can also pass constants into process plugins, which are prefixed with `constants/`.

#### Destination

The `destination` section contains the configuration that describes what gets loaded into Drupal.
```yml
destination:
  plugin: 'entity:file'
  type: image
```
You can create any type of content entity in Drupal. In this case, we're making file entities.  Specifically, we're making images, which are a special type of file entity. #fixme You can only make one type at a time, but more can be created using plugins during the Process step?

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

The uri we're constructing is a stream wrapper of the form `scheme://path/to/file`.  Islandora uses `flysystem`, which allows for integration with many different types of filesystems, both remote and local.  With `flysystem`, the scheme part of the uri is the name of a filesystem.  By default, Fedora is exposed using the scheme `fedora://`.  So by setting uri to `fedora://csv_migration/Free Smells.jpg`, we're saying "put Free Smells.jpg in the csv_migration directory in Fedora." #fixme is this a permanent location? Should you use something meaningful instead of "csv_migration" as a directory? In Drupal, this has no effect on how data is organized or presented, but what about in Pure Fedora?#fixme what does it mean to be in Fedora since Fedora can use other external storage or other things as its filesystem?

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
  # you're working with a property of a field
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
    # 'name' is the string value of the term, e.g. 'Original file', 'Thumnbnail'.
    value_key: name 
    bundle_key: vid
    bundle: islandora_models
```
The `entity_lookup` process plugin looks up an entity based on the configuration you give it.  You use the `entity_type`, `bundle_key`, and `bundle` configurations to limit which entities you search through.  `entity_type` is, as you'd suspect, the type of entity: node, media, file, taxonomy_term, etc...  `bundle_key` tells the migrate framework which property holds the bundle of the entity, and `bundle` is the actual bundle id you want to restrict by.  The search value you're looking for is the `source` configuration.  In this case we're looking for the string "Image", which we've defned as a constant.  And we're comparing it to the `name` field on each term by setting the `value_key` config.

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

Some fields don't hold just a single type of value.  In other words, not everything is just text, numbers, or references.  Using the Typed Data API, fields can hold groups of named values with different types.  Consider a field that holds an RGB color.  You could set it with PHP like so:
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

In `controlled_access_terms`, we have a notion of a `typed_relation`, which is an entity reference coupled with a MARC relator.  It expects an associative array that looks like this:
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
from anywhere within the Drupal installation directory will fire off the migration.  Go to http://localhost:8000/admin/content and you should see five new nodes.  Click on one, though, and you'll see it's just a stub with metadata.  The csv metadata is there, links to other entities like subjects and photographers are there, but there's no trace of the corresponding files.  Here's where media entities come into play.

## Migrating Media

Media entities are Drupal's solution for fieldable files.  Since you can't put fields on a file, what you can do is wrap the file with a Media entity.  In addition to a file reference, technical and structural metadata for the file go on the Media entity.  For example, mimetype, file size, resolution, etc... all belong on a Media entity.  Media also have a few special fields that are required for Islandora, `field_media_of` and `field_use`, which denote what node owns the media and what role the media serves, repectively.  Since the Media entity references both the file it wraps and the node that owns it, Media entities act as a bridge between files and nodes, tying them together.  And to do this, we make use of one last process plugin, `migration_lookup`.  Open up `/var/www/html/drupal/web/modules/contrib/migrate_islandora_csv/config/install/migrate_plus.migration.media.yml` and give it a look.

```yml
# Uninstall this config when the feature is uninstalled
dependencies:
  enforced:
    module:
      - migrate_islandora_csv 

id: media 
label: Import Media from CSV 
migration_group: migrate_islandora_csv

source:
  plugin: csv
  path: modules/contrib/migrate_islandora_csv/data/migration.csv

  # 1 means you have a header row, 0 means you don't
  header_row_count: 1

  # Each migration needs a unique key per row in the csv.  Here we're using the file path.
  keys:
    - file 

  # You can't enter string literals into a process plugin, but you can give it a constant as a 'source'.
  constants:
    # We're tagging our media as Original Files 
    use: Original File 

    # Everything gets created as admin
    uid: 1

process:

  name: title
  uid: constants/uid

  # Make the media an 'Original File'
  field_media_use:
    plugin: entity_lookup
    source: constants/use
    entity_type: taxonomy_term
    value_key: name 
    bundle_key: vid
    bundle: islandora_media_use 

  # Lookup the migrated file in the file migration.
  field_media_image:
    plugin: migration_lookup
    source: file 
    migration: file 
    no_stub: true

  # Lookup the migrated node in the node migration
  field_media_of:
    plugin: migration_lookup
    source: file 
    migration: node 
    no_stub: true
    
destination:
  # These are 'image' media we're making.
  plugin: 'entity:media'
  default_bundle: image 

migration_dependencies:
  required:
    - migrate_plus.migration.file
    - migrate_plus.migration.node
  optional: {  }
```

Compared to the other migrations, this one is very straightforward.  There's no string or array manipulation in yml, and at most there's only one process plugin per field. Title and user are set directly, with no processing required
```yml
  name: title
  uid: constants/uid
```
The `field_media_use` field is a tag that's used to denote the purpose of a file with regard to the node it belongs to.  E.g. is this the original file? a lower quality derivative? thumbnail? etc...  In many ways it bears a resemblance to DSID in Islandora 7.x.  Like `field_model` with nodes, the vocabulary already exists in your Islandora install, so all you have to do is look it up with the `entity_lookup` plugin.
```yml
  # Make the media an 'Original File'
  field_media_use:
    plugin: entity_lookup
    source: constants/use
    entity_type: taxonomy_term
    value_key: name 
    bundle_key: vid
    bundle: islandora_media_use 
```
The `field_media_image` and `field_media_of` fields are how the media binds a file to a node.  You could use `entity_lookup` or `entity_generate`, but we've already migrated them and can very easily look them up by the id assigned to them during migration.  But what's the benefit of doing so?  The `entity_lookup` and `entity_generate` process plugins do the job fine, right?

The main advantage of using `migration_lookup` and defining migrations whenever possible, is that migrated entites can be rolled back.  If you were to hop into your console and execute
```bash
drush migrate:rollback --group migrate_islandora_csv
```
Your nodes, media, and files would all be gone.  But your subjects and photographers would remain.  If you want to truly and cleanly roll back every entity in a migration, you need to define those migrations and use `migration_lookup` to set entity reference fields.

## What have we learned?

If you've made it all the way to the end here, then you've learned that you can migrate files and CSV metadata into Islandora using only yml files.  You've seen how to transform data with pipelines of processing plugins and can handle numeric, text, and entity reference fields.  You can handle multiple values for fields, and even more complicated things like `typed_relation` fields.  And as big as this walkthrough was, we're only scratching the surface of what can be done with the Migrate API.

## Where to go from here?

There's certainly more you can do with Drupal 8's Migrate API.  There's a plethora of source and processing plugins out there that can handle pretty much anything you throw at it.  XML and JSON are fair game.  You can also request sources using HTTP, so you can always point it at an existing systems REST API and go from there.  Eventually, you'll also have to write your own process plugin if you can't make your logic work with the Migrate API's sometimes awkward workflow.

But really the best thing to do is try and get your data into Islandora!  You can use the `boilerplate` branch of this repository to clone down an empty migration, ready for you to customize to fit your data.  And as you assmble it into CSV format, keep in mind that if you have more than just names for things like subjects and authors, that you can always make more CSVs.  Think of it like maintaining tables in an SQL database.  Each CSV has unique keys, so you can lookup/join entiities between CSVs using those keys.  And you can still pipe delimit the keys like we did in our example to handle multi-vlaued fields.

The best part is, if you maintain these CSVs, they can be used to make bulk updates to metadata.  Just make your changes, then run the migration(s) again with the `--update` flag.  It might not be the most efficient way to do it, as you'll update every entity, even if it didn't change.  But you can manage that by breaking things down per collection or object type.  If you can keep the CSVs sufficiently small, it's a viable solution for a small repository.
