# Local Dev Tools: a small set of web dev helpers

The package provides a small set of (hopefully) useful web development
helpers that may be run from command line. Its main target is to make boring
tasks less boring and less time-consuming. It helps you setting up new projects
locally (non-container based ones), copying remote databases, setting up local
ones, etc.

It will always ask you to define and/or confirm any missing option, so you can
just run the commands without setting any options at all and you will be prompted
for any missing option.

## Installation
You probably want to install this package globally via composer:
```
composer global require glowpointzero/localdevtools
```
Make sure the global composer 'bin' directory is in your paths and run `dev list`
to show a description of all available commands. Alternatively (not recommended,
though), clone the repository to wherever and run `bin/dev list`.

#### Project structure assumptions
Some commands (such as the 'setup' and the 'project:create' assume that you organize
your local projects in one single directory containing subdirectories for every
project:
```
/all_my_projects
├── acme/
    ├── logs/
    ├── project_files/
        ├── public_html/
        ├── ...
```

During setup (`dev setup`) you'll be asked to set the 'projects root path', which would
correspond to the root directory 'all_my_projects' in the example above. A directory
that contains subdirectories to each of your projects.
'logs' is statically called 'logs' and be linked in in the virtual host configuration
file. 'project_files' corresponds to the 'Project files root directory name' you'll
be asked to set on each `dev project:create`. Same for the
'document root directory name', which corresponds to the 'public_html' directory
in the example above.


## Commands

### setup
Run `dev setup` to set up your local dev tools configuration. You'll be asked
for various things the local dev tools will need to take care of things in
the future. This includes your local database root user or the directory
path to where you store your virtual host configuration files.

Configuration will be stored in `~/.localdevtools/config`.


### link:setup
Run `dev link:setup` to define an infinite (yes, in-fi-nite!) number of
symlink sources/targets to use later by callig `dev link`.

### link
`dev link` lets you create any symlink previously defined by `dev link:setup`.
"Why would I need to to that?" you may ask. Imagine switching to different
php.inis or even different local servers, etc. at the push of a few buttons.

### code:fix
When in need to fix your code according to coding standards, hit up `dev code:fix`.
You'll be asked for the coding standards to apply and if you have a php-cs-fixer
config file, you may even point to this.

As mentioned, this is a quick wrapper to the [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer).

### configuration:diagnose
`dev configuration:diagnose` will run through all setting you've  defined
during the `dev setup` process and will try to find any conflicts or errors.

### db:copyfromremote
`dev db:copyfromremote` will - you've probably guessed it - copy a database
from a remote host without any of the manual hassle. If your local DB exists,
a dump will be created before overwriting it.

### db:create
Create a local database by running `dev db:create`. That's it.

### db:dump
Dump any of your databases into `~/dumps` by running `dev db:dump`.

### db:import
Import any database dump existing as file on your local machine. Simply
run `dev db:import`.

### project:create
When setting up a new local web project, there are a few things that need to 
be done before really being able to work on a site. `project:create` takes
care of
- creating the needed directories
- creating the virtual host files
- creating a database or importing an existing one
- extending the 'hosts' file
- cloning a project git repository and possibly running composer actions

### server:restart
A very simple wrapper to the server restart command you set during `dev setup`.

## Release History
See the [CHANGELOG](CHANGELOG).