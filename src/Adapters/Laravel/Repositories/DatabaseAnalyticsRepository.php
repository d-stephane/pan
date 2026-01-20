<?php

declare(strict_types=1);

namespace Pan\Adapters\Laravel\Repositories;

use Illuminate\Support\Facades\DB;
use Pan\Contracts\AnalyticsRepository;
use Pan\Enums\EventType;
use Pan\PanConfiguration;
use Pan\ValueObjects\Analytic;

/**
 * @internal
 */
final readonly class DatabaseAnalyticsRepository implements AnalyticsRepository
{
    /**
     * Creates a new analytics repository instance.
     */
    public function __construct(private PanConfiguration $config)
    {
        //
    }

    /**
     * Returns all analytics.
     *
     * @return array<int, Analytic>
     */
    public function all(): array
    {
        /** @var array<int, Analytic> $all */
        $all = DB::table('pan_analytics')->get()->map(fn (mixed $analytic): Analytic => new Analytic(
            id: (int) $analytic->id, // @phpstan-ignore-line
            name: $analytic->name, // @phpstan-ignore-line
            impressions: (int) $analytic->impressions, // @phpstan-ignore-line
            hovers: (int) $analytic->hovers, // @phpstan-ignore-line
            clicks: (int) $analytic->clicks, // @phpstan-ignore-line
        ))->toArray();

        return $all;
    }

    /**
     * Increments the given event for the given analytic.
     */
    public function increment(string $name, EventType $event, string $ip): void
    {
        [
            'allowed_analytics' => $allowedAnalytics,
            'max_analytics' => $maxAnalytics,
        ] = $this->config->toArray();

        if (count($allowedAnalytics) > 0 && ! in_array($name, $allowedAnalytics, true)) {
            return;
        }

        if (DB::table('pan_analytics')->where('name', $name)->count() === 0) {
            if (DB::table('pan_analytics')->count() < $maxAnalytics) {
                DB::table('pan_analytics')->insert(['name' => $name, $event->column() => 1]);
            }

            return;
        }

        DB::table('pan_analytics')->where('name', $name)->increment($event->column());

        $this->addStats($name, $event, $ip);
    }

    /**
     * Flush all analytics.
     */
    public function flush(): void
    {
        DB::table('pan_analytics')->truncate();
    }


    // -----------------------------------------------------------------------------------------------------------------------------------------------


    /**
     * @return void
     */
    private function addStats(string $name, EventType $event, string $ip): void
    {
        $field = $event->column();

        DB::insert('
            INSERT INTO pan_stats (pan_ip_id, name, '.$field.') VALUES (?, ?, ?)
            ON CONFLICT ON CONSTRAINT u_pan_stats_pan_ip_id_name DO UPDATE SET
                pan_ip_id = pan_stats.pan_ip_id,
                name = pan_stats.name,
                '.$field.' = pan_stats.'.$field.' + 1',
            [$this->checkIP($ip), $name, 1]
        );
    }


    /**
     * @return int
     */
    private function checkIP(string $ip): int
    {
        $id = DB::select('SELECT id FROM pan_ip WHERE ip = ?', [$ip]);

        return (empty($id)) ? $this->addIP($ip) : $id[0]->id;
    }


    /**
     * @return int
     */
    private function addIP(string $ip): int
    {
        return DB::table('pan_ip')->insertGetId(['ip' => $ip]);
    }
}
