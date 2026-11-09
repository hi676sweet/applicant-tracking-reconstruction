<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HiringWorkflow
{
    public function authorize(User $user, ?Application $application = null): void
    {
        abort_unless($user->canRecruit(), 403);
        abort_if($application && $application->organization_id !== $user->organization_id, 404);
    }

    public function record(User $actor, string $event, string $description, ?Application $application = null): ActivityLog
    {
        return ActivityLog::create(['organization_id' => $actor->organization_id, 'actor_id' => $actor->id, 'application_id' => $application?->id, 'event' => $event, 'description' => $description]);
    }

    public function addApplication(User $actor, JobOpening $job, array $candidate): Application
    {
        $this->authorize($actor);
        abort_unless($job->organization_id === $actor->organization_id, 404);

        return DB::transaction(function () use ($actor, $job, $candidate): Application {
            Organization::whereKey($actor->organization_id)->increment('lock_version');
            if ($job->fresh()->status !== 'open') {
                throw ValidationException::withMessages(['job_opening_id' => 'This role is closed to new applications.']);
            }
            $person = Candidate::firstOrCreate(['organization_id' => $actor->organization_id, 'email' => mb_strtolower($candidate['email'])], ['name' => $candidate['name'], 'headline' => $candidate['headline'] ?? null]);
            if (Application::where('job_opening_id', $job->id)->where('candidate_id', $person->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'This candidate already has an application for the role.']);
            }
            $application = Application::create(['organization_id' => $actor->organization_id, 'job_opening_id' => $job->id, 'candidate_id' => $person->id, 'stage' => 'applied']);
            $this->record($actor, 'application.created', 'Application added for '.$person->name.'.', $application);

            return $application;
        }, 3);
    }

    public function move(User $actor, Application $application, string $target, int $version): Application
    {
        $this->authorize($actor, $application);

        return DB::transaction(function () use ($actor, $application, $target, $version): Application {
            Organization::whereKey($actor->organization_id)->increment('lock_version');
            $current = $application->fresh();
            if ($current->version !== $version) {
                throw ValidationException::withMessages(['stage' => 'This application changed in another tab. Reload before moving it.']);
            }
            if (! in_array($target, Application::TRANSITIONS[$current->stage] ?? [], true)) {
                throw ValidationException::withMessages(['stage' => 'That pipeline transition is not allowed.']);
            }
            if ($target !== 'rejected' && $current->jobOpening->status !== 'open') {
                throw ValidationException::withMessages(['stage' => 'Reopen the role before advancing applications.']);
            }
            if (in_array($target, ['offer', 'rejected'], true) && $current->interviews()->where('status', 'scheduled')->where('ends_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['stage' => 'Cancel upcoming interviews before moving out of the interview stage.']);
            }
            $changed = Application::whereKey($current->id)->where('version', $version)->update(['stage' => $target, 'version' => $version + 1, 'updated_at' => now()]);
            if ($changed !== 1) {
                throw ValidationException::withMessages(['stage' => 'This application changed. Reload and try again.']);
            }
            $this->record($actor, 'stage.changed', ucfirst($current->stage).' → '.ucfirst($target).'.', $current);

            return $current->fresh();
        }, 3);
    }

    public function schedule(User $actor, Application $application, User $interviewer, CarbonImmutable $start, CarbonImmutable $end): Interview
    {
        $this->authorize($actor, $application);
        abort_unless($interviewer->organization_id === $actor->organization_id && $interviewer->canRecruit(), 422);
        if ($start->isPast() || $end->diffInMinutes($start, true) < 30 || $end->diffInMinutes($start, true) > 180 || $end <= $start) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a future interview lasting between 30 and 180 minutes.']);
        }

        return DB::transaction(function () use ($actor, $application, $interviewer, $start, $end): Interview {
            // The first write serializes competing SQLite schedulers before reading availability.
            Organization::whereKey($actor->organization_id)->increment('lock_version');
            if ($application->fresh()->stage !== 'interview' || $application->jobOpening->status !== 'open') {
                throw ValidationException::withMessages(['starts_at' => 'Move this application to Interview before scheduling.']);
            }
            $conflict = Interview::where('organization_id', $actor->organization_id)->where('status', 'scheduled')
                ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->where(fn ($query) => $query->where('interviewer_id', $interviewer->id)->orWhereHas('application', fn ($applications) => $applications->where('candidate_id', $application->candidate_id)))->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'The interviewer or candidate already has an overlapping interview.']);
            }
            $interview = Interview::create(['organization_id' => $actor->organization_id, 'application_id' => $application->id, 'interviewer_id' => $interviewer->id, 'starts_at' => $start, 'ends_at' => $end]);
            $this->record($actor, 'interview.scheduled', 'Interview with '.$interviewer->name.' on '.$start->format('M j, H:i').' UTC.', $application);

            return $interview;
        }, 3);
    }

    public function cancelInterview(User $actor, Interview $interview): void
    {
        $this->authorize($actor, $interview->application);
        DB::transaction(function () use ($actor, $interview): void {
            Organization::whereKey($actor->organization_id)->increment('lock_version');
            $changed = Interview::whereKey($interview->id)->where('status', 'scheduled')->update(['status' => 'cancelled', 'updated_at' => now()]);
            if ($changed !== 1) {
                throw ValidationException::withMessages(['interview' => 'This interview is already cancelled.']);
            }
            $this->record($actor, 'interview.cancelled', 'Interview cancelled; the time slot is available again.', $interview->application);
        }, 3);
    }
}
