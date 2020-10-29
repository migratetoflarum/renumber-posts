# Renumber Posts

This extension re-numbers posts in discussions on demand.

Why you might need this extension:

- You manually imported data into your forum and need to create the number values
- You want to clean existing data with a lot of missing numbers between posts and don't care about the permalinks

I strongly recommend you test the command on a backup first before running it on your live data!

## Installation and update

Compatible with both beta 13 and beta 14.

    composer require migratetoflarum/renumber-posts

## Documentation

The extension is implemented as a command line utility.
Enable the extension in Flarum admin panel, then open a terminal and `cd` into the Flarum folder.

Running the command without any parameter will perform a dry run (does not update anything):

    php flarum migratetoflarum:renumber

You can enable or disable various calculations with command options.

For example, to only add numbers to posts without number:

    php flarum migratetoflarum:renumber --partial --missing

To number every post of every discussion chronologically:

    php flarum migratetoflarum:renumber --all

To re-number only discussions where duplicate post numbers were found

    php flarum migratetoflarum:renumber --fix-duplicates

To get a list of all available options, run:

    php flarum help migratetoflarum:renumber

## Removal

When you are done with the update, you can safely remove the extension.
It does not need to stay installed.

    composer remove migratetoflarum/renumber-posts

## Links

- [Source code on GitHub](https://github.com/migratetoflarum/renumber-posts)
- [Report an issue](https://github.com/migratetoflarum/renumber-posts/issues)
- [Download via Packagist](https://packagist.org/packages/migratetoflarum/renumber-posts)

The initial version of this extension was sponsored by [@Wadera](https://discuss.flarum.org/u/Wadera)
