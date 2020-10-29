<?php

namespace MigrateToFlarum\RenumberPosts\Commands;

use Flarum\Discussion\Discussion;
use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\UrlGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RenumberCommand extends Command
{
    protected $signature = 'migratetoflarum:renumber';
    protected $description = 'Re-number posts of the forum';

    public function __construct()
    {
        parent::__construct();

        $this->addOption('all', null, null, 'Re-number all discussions, even if the numbers were ok. --fix-duplicates and --fix-disorder would be redundant');
        $this->addOption('partial', null, null, 'Add missing numbers to partially numbered discussion');
        $this->addOption('missing', null, null, 'Add numbers to discussions without numbers');
        $this->addOption('fix-duplicates', null, null, 'Completely re-number discussions that had duplicates. Affects all discussions unless --partial is used, in which case only partially numbered discussions will be fixed');
        $this->addOption('fix-disorder', null, null, 'Completely re-number discussions that had disorder. Affects all discussions unless --partial is used, in which case only partially numbered discussions will be fixed');
        $this->addOption('enumerate', null, null, 'Outputs links to discussions with duplicates and disorder');
    }

    public function handle()
    {
        $totals = new \stdClass();
        $totals->good = 0;
        $totals->goodWithDuplicates = 0;
        $totals->goodWithDisorder = 0;
        $totals->partial = 0;
        $totals->partialWithDuplicates = 0;
        $totals->partialWithDisorder = 0;
        $totals->missing = 0;

        $discussionsUpdated = 0;
        $postsUpdated = 0;

        $discussionCount = Discussion::query()->count();

        $progress = $this->output->createProgressBar($discussionCount);

        Discussion::query()->each(function (Discussion $discussion) use ($totals, &$discussionsUpdated, &$postsUpdated, $progress) {
            // ORDER BY id helps in case posts have an identical date, in which case we want to use the insert order
            $postsWithNumbers = $discussion->posts()->whereNotNull('number')->orderBy('created_at')->orderBy('id')->get();
            $postsWithoutNumbers = $discussion->posts()->whereNull('number')->orderBy('created_at')->orderBy('id')->get();

            $numbers = $postsWithNumbers->pluck('number');

            $hasDuplicates = $numbers->unique()->count() !== $numbers->count();

            $hasDisorder = false;
            $numbers->reduce(function ($previousNumber, $number) use (&$hasDisorder) {
                if ($previousNumber > $number) {
                    $hasDisorder = true;
                }

                return $number;
            }, 0);

            if ($postsWithoutNumbers->count() === 0) {
                $totals->good++;

                if ($hasDuplicates) {
                    $totals->goodWithDuplicates++;
                    $this->enumerate('DUPLICATE', $discussion);
                }

                if ($hasDisorder) {
                    $totals->goodWithDisorder++;
                    $this->enumerate('DISORDER', $discussion);
                }

                if (
                    $this->option('all') ||
                    ($hasDuplicates && $this->option('fix-duplicates') && !$this->option('partial')) ||
                    ($hasDisorder && $this->option('fix-disorder') && !$this->option('partial'))
                ) {
                    $this->renumberFrom($postsWithNumbers, $discussion);
                    $discussionsUpdated++;
                    $postsUpdated += $postsWithNumbers->count();
                }
            } else if ($postsWithNumbers->count() === 0) {
                $totals->missing++;

                $this->enumerate('MISSING', $discussion);

                if ($this->option('all') || $this->option('missing')) {
                    $this->renumberFrom($postsWithoutNumbers, $discussion, false);
                    $discussionsUpdated++;
                    $postsUpdated += $postsWithoutNumbers->count();
                }
            } else {
                $totals->partial++;

                $this->enumerate('PARTIAL', $discussion);

                if ($hasDuplicates) {
                    $totals->partialWithDuplicates++;
                    $this->enumerate('DUPLICATE', $discussion);
                }

                if ($hasDisorder) {
                    $totals->partialWithDisorder++;
                    $this->enumerate('DISORDER', $discussion);
                }

                if (
                    $this->option('all') ||
                    ($hasDuplicates && $this->option('fix-duplicates') && $this->option('partial')) ||
                    ($hasDisorder && $this->option('fix-disorder') && $this->option('partial'))
                ) {
                    $posts = $discussion->posts()->orderBy('created_at')->orderBy('id')->get();

                    $this->renumberFrom($posts, $discussion);
                    $discussionsUpdated++;
                    $postsUpdated += $posts->count();
                } else if ($this->option('partial')) {
                    $this->renumberFrom($postsWithoutNumbers, $discussion, false, $numbers->max());
                    $discussionsUpdated++;
                    $postsUpdated += $postsWithoutNumbers->count();
                }
            }

            $progress->advance();
        });

        $progress->finish();
        $this->info('');

        $this->info("Total discussions: $discussionCount");
        $this->info("Discussions completely numbered: $totals->good");
        $this->info("-- with duplicates: $totals->goodWithDuplicates");
        $this->info("-- with disorder: $totals->goodWithDisorder");
        $this->info("Discussions partially numbered: $totals->partial");
        $this->info("-- with duplicates: $totals->partialWithDuplicates");
        $this->info("-- with disorder: $totals->partialWithDuplicates");
        $this->info("Discussions not numbered: $totals->missing");
        $this->info('');
        $this->info("Updated discussions: $discussionsUpdated");
        $this->info("Updated posts: $postsUpdated");
    }

    protected function renumberFrom(Collection $posts, Discussion $discussion, $clear = true, $previousNumber = 0)
    {
        if ($clear) {
            $discussion->posts()->update(['number' => null]);
        }

        $number = $previousNumber;

        foreach ($posts as $post) {
            $post->number = ++$number;
            $post->save();
        }

        $discussion->post_number_index = $number;
        $discussion->save();
    }

    protected function enumerate(string $type, Discussion $discussion)
    {
        if (!$this->option('enumerate')) {
            return;
        }

        /**
         * @var $url RouteCollectionUrlGenerator
         */
        $url = app(UrlGenerator::class)->to('forum');

        $this->info($type . ': ' . $url->route('discussion', [
                'id' => $discussion->id,
            ]));
    }
}
