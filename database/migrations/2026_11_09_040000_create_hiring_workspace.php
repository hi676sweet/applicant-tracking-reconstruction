<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->constrained();
            $table->string('role')->default('recruiter');
        });
        Schema::create('job_openings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('title');
            $table->string('department');
            $table->string('location');
            $table->string('employment_type')->default('Full-time');
            $table->string('status')->default('open');
            $table->timestamps();
        });
        Schema::create('candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name');
            $table->string('email');
            $table->string('headline')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'email']);
        });
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('job_opening_id')->constrained();
            $table->foreignId('candidate_id')->constrained();
            $table->string('stage')->default('applied');
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['job_opening_id', 'candidate_id']);
        });
        Schema::create('interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('application_id')->constrained();
            $table->foreignId('interviewer_id')->constrained('users');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->index(['interviewer_id', 'starts_at', 'ends_at']);
        });
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('application_id')->nullable()->constrained();
            $table->string('event');
            $table->string('description', 500);
            $table->timestamp('created_at')->useCurrent();
        });
        DB::unprepared("CREATE TRIGGER activity_logs_no_update BEFORE UPDATE ON activity_logs BEGIN SELECT RAISE(ABORT, 'Audit records are immutable'); END");
        DB::unprepared("CREATE TRIGGER activity_logs_no_delete BEFORE DELETE ON activity_logs BEGIN SELECT RAISE(ABORT, 'Audit records are immutable'); END");
    }

    public function down(): void
    {
        foreach (['activity_logs', 'interviews', 'applications', 'candidates', 'job_openings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['organization_id', 'role']);
        });
        Schema::dropIfExists('organizations');
    }
};
