<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $northstar = Organization::firstOrCreate(['name' => 'Northstar Studio']);
        $orbit = Organization::firstOrCreate(['name' => 'Orbit Labs']);
        foreach ([['Alex Rowan', 'alex@northstar.test', 'admin', $northstar], ['Sam Rivers', 'sam@northstar.test', 'recruiter', $northstar], ['Casey Demo', 'viewer@northstar.test', 'viewer', $northstar], ['Robin Wells', 'robin@orbit.test', 'admin', $orbit]] as [$name, $email, $role, $organization]) {
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'demo-password', 'role' => $role, 'organization_id' => $organization->id]);
        }
        $alex = User::where('email', 'alex@northstar.test')->firstOrFail();
        $sam = User::where('email', 'sam@northstar.test')->firstOrFail();
        $jobs = [];
        foreach ([['Product Designer', 'Design', 'Remote'], ['Frontend Engineer', 'Engineering', 'London · Hybrid'], ['Customer Success Lead', 'Customer Experience', 'Remote']] as [$title, $department, $location]) {
            $jobs[] = JobOpening::firstOrCreate(['organization_id' => $northstar->id, 'title' => $title], compact('department', 'location') + ['employment_type' => 'Full-time']);
        }
        $people = [
            ['Maya Chen', 'maya@candidate.test', 'Thoughtful product design · 5 years', 0, 'screening'],
            ['Jonah Brooks', 'jonah@candidate.test', 'Interfaces with a human touch', 0, 'applied'],
            ['Avery Patel', 'avery@candidate.test', 'React, TypeScript and a little curiosity', 1, 'interview'],
            ['Riley Morgan', 'riley@candidate.test', 'Building lasting customer relationships', 2, 'offer'],
            ['Noah Ellis', 'noah@candidate.test', 'Design systems · Accessible experiences', 0, 'hired'],
            ['Lena Park', 'lena@candidate.test', 'A builder who cares about the details', 1, 'applied'],
            ['Elliot Reed', 'elliot@candidate.test', 'Customer experience · 6 years', 2, 'screening'],
            ['Sofia Vale', 'sofia@candidate.test', 'Digital products, made with purpose', 0, 'interview'],
            ['Theo Bennett', 'theo@candidate.test', 'Frontend development · 3 years', 1, 'rejected'],
            ['Amara Stone', 'amara@candidate.test', 'Turning complex things into simple ones', 1, 'applied'],
        ];
        foreach ($people as [$name, $email, $headline, $jobIndex, $stage]) {
            $candidate = Candidate::firstOrCreate(['organization_id' => $northstar->id, 'email' => $email], compact('name', 'headline'));
            $application = Application::firstOrCreate(['job_opening_id' => $jobs[$jobIndex]->id, 'candidate_id' => $candidate->id], ['organization_id' => $northstar->id, 'stage' => $stage]);
            if ($application->wasRecentlyCreated) {
                ActivityLog::create(['organization_id' => $northstar->id, 'actor_id' => $alex->id, 'application_id' => $application->id, 'event' => 'demo.initialized', 'description' => 'Fictional application initialized in '.ucfirst($stage).'.']);
            }
            if ($email === 'avery@candidate.test' && ! $application->interviews()->exists()) {
                Interview::create(['organization_id' => $northstar->id, 'application_id' => $application->id, 'interviewer_id' => $sam->id, 'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(11, 0)]);
            }
        }
        $robin = User::where('email', 'robin@orbit.test')->firstOrFail();
        $job = JobOpening::firstOrCreate(['organization_id' => $orbit->id, 'title' => 'Orbit Research Engineer'], ['department' => 'Research', 'location' => 'Remote', 'employment_type' => 'Contract']);
        $candidate = Candidate::firstOrCreate(['organization_id' => $orbit->id, 'email' => 'winter@orbit-candidate.test'], ['name' => 'Winter Gray', 'headline' => 'Orbit-only candidate']);
        $application = Application::firstOrCreate(['job_opening_id' => $job->id, 'candidate_id' => $candidate->id], ['organization_id' => $orbit->id, 'stage' => 'screening']);
        if ($application->wasRecentlyCreated) {
            ActivityLog::create(['organization_id' => $orbit->id, 'actor_id' => $robin->id, 'application_id' => $application->id, 'event' => 'demo.initialized', 'description' => 'Fictional Orbit application initialized.']);
        }
    }
}
