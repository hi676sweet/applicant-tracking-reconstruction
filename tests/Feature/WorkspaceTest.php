<?php

namespace Tests\Feature;

use App\Livewire\Pipeline;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function recruiter(): User
    {
        return User::where('email', 'alex@northstar.test')->firstOrFail();
    }

    public function test_guests_are_redirected_and_demo_login_authenticates(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->post('/login', ['email' => 'alex@northstar.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'alex@northstar.test', 'password' => 'demo-password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->recruiter());
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_pipeline_search_and_role_filter_are_organization_scoped(): void
    {
        $this->actingAs($this->recruiter());
        $role = JobOpening::where('title', 'Frontend Engineer')->firstOrFail();
        Livewire::test(Pipeline::class)->assertSee('Maya Chen')->assertDontSee('Winter Gray')
            ->set('search', 'Maya')->assertSee('Maya Chen')->assertDontSee('Jonah Brooks')
            ->set('search', '')->set('job', (string) $role->id)->assertSee('Avery Patel')->assertDontSee('Maya Chen');
        $foreign = JobOpening::where('title', 'Orbit Research Engineer')->firstOrFail();
        Livewire::test(Pipeline::class)->set('job', (string) $foreign->id)->assertDontSee('Winter Gray')->assertSee('No candidates in this view.');
    }

    public function test_foreign_profiles_writes_and_activity_are_hidden(): void
    {
        $foreign = Application::whereHas('candidate', fn ($q) => $q->where('email', 'winter@orbit-candidate.test'))->firstOrFail();
        $this->actingAs($this->recruiter());
        $this->get('/applications/'.$foreign->id)->assertNotFound();
        $this->post('/applications/'.$foreign->id.'/stage', ['stage' => 'interview', 'version' => 0])->assertNotFound();
        $this->get('/activity')->assertOk()->assertDontSee('Fictional Orbit application initialized.');
        $this->get('/jobs')->assertOk()->assertDontSee('Orbit Research Engineer');
        $this->assertDatabaseHas('applications', ['id' => $foreign->id, 'stage' => 'screening', 'version' => 0]);
    }

    public function test_read_only_users_can_read_but_cannot_mutate(): void
    {
        $viewer = User::where('email', 'viewer@northstar.test')->firstOrFail();
        $this->actingAs($viewer)->get('/')->assertOk();
        $this->get('/applications/1')->assertOk()->assertSee('read-only access');
        $this->get('/applications/new')->assertForbidden();
        $this->post('/applications/1/stage', ['stage' => 'interview', 'version' => 0])->assertForbidden();
        $this->post('/jobs', ['title' => 'Unauthorized Role'])->assertForbidden();
    }

    public function test_new_application_is_recorded_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->recruiter());
        $job = JobOpening::where('title', 'Product Designer')->firstOrFail();
        $data = ['job_opening_id' => $job->id, 'name' => 'Taylor Demo', 'email' => 'taylor@candidate.test', 'headline' => 'Designer'];
        $this->post('/applications', $data)->assertRedirect();
        $person = Candidate::where('email', $data['email'])->firstOrFail();
        $application = Application::where('candidate_id', $person->id)->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['application_id' => $application->id, 'event' => 'application.created']);
        $this->post('/applications', $data)->assertSessionHasErrors('email');
        $this->assertDatabaseCount('applications', 12);
        $this->assertDatabaseCount('candidates', 12);
    }

    public function test_real_email_domains_foreign_roles_and_closed_roles_are_rejected(): void
    {
        $this->actingAs($this->recruiter());
        $job = JobOpening::where('title', 'Product Designer')->firstOrFail();
        $data = ['job_opening_id' => $job->id, 'name' => 'Taylor Demo', 'email' => 'taylor@example.com'];
        $this->post('/applications', $data)->assertSessionHasErrors('email');
        $data['email'] = 'taylor@example.test';
        $data['job_opening_id'] = JobOpening::where('title', 'Orbit Research Engineer')->value('id');
        $this->post('/applications', $data)->assertSessionHasErrors('job_opening_id');
        $job->update(['status' => 'closed']);
        $data['job_opening_id'] = $job->id;
        $this->post('/applications', $data)->assertSessionHasErrors('job_opening_id');
        $this->assertDatabaseCount('applications', 11);
        $this->assertDatabaseCount('candidates', 11);
    }

    public function test_create_role_uses_actor_organization_and_writes_audit_atomically(): void
    {
        $actor = $this->recruiter();
        $this->actingAs($actor);
        $this->post('/jobs', ['title' => 'Demo Data Engineer', 'department' => 'Engineering', 'location' => 'Remote', 'employment_type' => 'Contract', 'organization_id' => 999])->assertRedirect();
        $this->assertDatabaseHas('job_openings', ['title' => 'Demo Data Engineer', 'organization_id' => $actor->organization_id]);
        $this->assertDatabaseHas('activity_logs', ['organization_id' => $actor->organization_id, 'actor_id' => $actor->id, 'event' => 'job.created']);
    }

    public function test_candidate_supplied_html_is_escaped(): void
    {
        $person = Candidate::where('email', 'maya@candidate.test')->firstOrFail();
        $person->update(['name' => '<script>alert(1)</script>']);
        $this->actingAs($this->recruiter())->get('/applications/1')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
