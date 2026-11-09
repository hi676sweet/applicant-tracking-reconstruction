<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\HiringWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HiringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private HiringWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actor = User::where('email', 'alex@northstar.test')->firstOrFail();
        $this->workflow = app(HiringWorkflow::class);
    }

    private function application(string $email = 'maya@candidate.test'): Application
    {
        return Application::whereHas('candidate', fn ($query) => $query->where('email', $email))->firstOrFail();
    }

    private function start(): CarbonImmutable
    {
        return CarbonImmutable::tomorrow('UTC')->setTime(14, 0);
    }

    public function test_legal_transitions_increment_version_and_record_actor(): void
    {
        $application = $this->application();
        $moved = $this->workflow->move($this->actor, $application, 'interview', 0);
        $this->assertSame('interview', $moved->stage);
        $this->assertSame(1, $moved->version);
        $this->assertDatabaseHas('activity_logs', ['application_id' => $application->id, 'actor_id' => $this->actor->id, 'event' => 'stage.changed']);
        $this->workflow->move($this->actor, $moved, 'offer', 1);
        $this->workflow->move($this->actor, $moved->fresh(), 'hired', 2);
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'stage' => 'hired', 'version' => 3]);
    }

    public function test_skipping_stages_or_reopening_a_terminal_decision_fails_without_audit(): void
    {
        $before = ActivityLog::count();
        foreach ([[$this->application(), 'hired'], [$this->application('noah@candidate.test'), 'screening']] as [$application, $target]) {
            try {
                $this->workflow->move($this->actor, $application, $target, 0);
                $this->fail('Invalid transition accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('stage', $exception->errors());
            }
        }
        $this->assertSame($before, ActivityLog::count());
    }

    public function test_a_stale_pipeline_form_cannot_overwrite_a_teammates_change(): void
    {
        $application = $this->application();
        $this->workflow->move($this->actor, $application, 'interview', 0);
        try {
            $this->workflow->move($this->actor, $application, 'rejected', 0);
            $this->fail('Stale write accepted');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('another tab', $exception->errors()['stage'][0]);
        }
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'stage' => 'interview', 'version' => 1]);
    }

    public function test_audit_insert_failure_rolls_back_the_pipeline_change(): void
    {
        DB::unprepared("CREATE TRIGGER reject_stage_audit BEFORE INSERT ON activity_logs WHEN NEW.event = 'stage.changed' BEGIN SELECT RAISE(ABORT, 'Simulated storage failure'); END");
        $application = $this->application();
        try {
            $this->workflow->move($this->actor, $application, 'interview', 0);
            $this->fail('Expected audit failure');
        } catch (QueryException) {
            $this->assertDatabaseHas('applications', ['id' => $application->id, 'stage' => 'screening', 'version' => 0]);
        }
    }

    public function test_interviewer_conflicts_reject_overlaps_but_allow_adjacent_slots(): void
    {
        $first = $this->application('avery@candidate.test');
        $second = $this->application('sofia@candidate.test');
        $start = $this->start();
        $this->workflow->schedule($this->actor, $first, $this->actor, $start, $start->addHour());
        try {
            $this->workflow->schedule($this->actor, $second, $this->actor, $start->addMinutes(30), $start->addMinutes(90));
            $this->fail('Overlap accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('starts_at', $exception->errors());
        }
        $this->workflow->schedule($this->actor, $second, $this->actor, $start->addHour(), $start->addHours(2));
        $this->assertDatabaseCount('interviews', 3);
    }

    public function test_one_candidate_cannot_be_double_booked_across_two_roles_and_interviewers(): void
    {
        $first = $this->application('avery@candidate.test');
        $job = JobOpening::where('title', 'Product Designer')->firstOrFail();
        $second = Application::create(['organization_id' => $this->actor->organization_id, 'candidate_id' => $first->candidate_id, 'job_opening_id' => $job->id, 'stage' => 'interview']);
        $sam = User::where('email', 'sam@northstar.test')->firstOrFail();
        $start = $this->start();
        $this->workflow->schedule($this->actor, $first, $this->actor, $start, $start->addHour());
        try {
            $this->workflow->schedule($this->actor, $second, $sam, $start, $start->addHour());
            $this->fail('Candidate double booking accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('starts_at', $exception->errors());
        }
        $this->assertDatabaseCount('interviews', 2);
    }

    public function test_cancelled_interviews_release_availability_and_are_audited(): void
    {
        $first = $this->application('avery@candidate.test');
        $second = $this->application('sofia@candidate.test');
        $start = $this->start();
        $interview = $this->workflow->schedule($this->actor, $first, $this->actor, $start, $start->addHour());
        $this->workflow->cancelInterview($this->actor, $interview);
        $this->workflow->schedule($this->actor, $second, $this->actor, $start, $start->addHour());
        $this->assertDatabaseHas('interviews', ['id' => $interview->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('activity_logs', ['application_id' => $first->id, 'event' => 'interview.cancelled']);
    }

    public function test_future_interviews_must_be_cancelled_before_offer_and_duplicate_cancel_fails(): void
    {
        $application = $this->application('sofia@candidate.test');
        $start = $this->start();
        $interview = $this->workflow->schedule($this->actor, $application, $this->actor, $start, $start->addHour());
        try {
            $this->workflow->move($this->actor, $application, 'offer', 0);
            $this->fail('Interview left orphaned');
        } catch (ValidationException) {
            $this->assertSame('interview', $application->fresh()->stage);
        }
        $this->workflow->cancelInterview($this->actor, $interview);
        $this->workflow->move($this->actor, $application, 'offer', 0);
        try {
            $this->workflow->cancelInterview($this->actor, $interview);
            $this->fail('Repeated cancellation accepted');
        } catch (ValidationException) {
            $this->assertSame('offer', $application->fresh()->stage);
        }
    }

    public function test_past_short_reversed_or_non_interview_schedules_are_rejected(): void
    {
        $application = $this->application('sofia@candidate.test');
        $start = $this->start();
        foreach ([[$start, $start->addMinutes(15)], [$start, $start->subHour()], [$start->subDays(3), $start->subDays(3)->addHour()]] as [$a, $b]) {
            try {
                $this->workflow->schedule($this->actor, $application, $this->actor, $a, $b);
                $this->fail('Invalid time accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('starts_at', $exception->errors());
            }
        }
        try {
            $this->workflow->schedule($this->actor, $this->application(), $this->actor, $start, $start->addHour());
            $this->fail('Non-interview stage scheduled');
        } catch (ValidationException) {
            $this->assertDatabaseCount('interviews', 1);
        }
    }

    public function test_audit_history_rejects_database_updates_and_deletes(): void
    {
        $event = ActivityLog::firstOrFail();
        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('activity_logs')->where('id', $event->id);
                $operation === 'update' ? $query->update(['description' => 'Tampered']) : $query->delete();
                $this->fail('Audit mutation accepted');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('activity_logs', ['id' => $event->id, 'description' => $event->description]);
    }

    public function test_cross_organization_interviewer_is_rejected_by_http(): void
    {
        $application = $this->application('sofia@candidate.test');
        $foreign = User::where('email', 'robin@orbit.test')->firstOrFail();
        $this->actingAs($this->actor)->post('/applications/'.$application->id.'/interviews', ['interviewer_id' => $foreign->id, 'starts_at' => $this->start()->format('Y-m-d\TH:i'), 'ends_at' => $this->start()->addHour()->format('Y-m-d\TH:i')])->assertSessionHasErrors('interviewer_id');
        $this->assertDatabaseCount('interviews', 1);
    }
}
