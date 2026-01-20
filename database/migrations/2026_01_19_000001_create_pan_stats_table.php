<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection()->getPdo()->exec('
            CREATE TABLE pan_ip
            (
                id SERIAL PRIMARY KEY,
                ip VARCHAR(15) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
            );

            CREATE TABLE pan_stats
            (
                id SERIAL PRIMARY KEY,
                pan_ip_id INTEGER NOT NULL,
                name VARCHAR NOT NULL,
                impressions BIGINT NOT NULL DEFAULT 0,
                hovers BIGINT NOT NULL DEFAULT 0,
                clicks BIGINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                CONSTRAINT u_id_pan_ip_name UNIQUE (pan_ip_id, name),
                CONSTRAINT fk_pan_stats_pan_ip_id FOREIGN KEY (pan_ip_id) REFERENCES pan_ip ON UPDATE CASCADE ON DELETE CASCADE
            );

            CREATE FUNCTION public.updated_at() RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP(0);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER pan_stats_updated_at BEFORE UPDATE ON pan_stats FOR EACH ROW EXECUTE PROCEDURE public.updated_at();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pan_stats');
        Schema::dropIfExists('pan_elements');
    }
};
