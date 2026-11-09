<?php

namespace App\Livewire;

use App\Models\Application;
use App\Models\Interview;
use App\Models\JobOpening;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Pipeline extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $job = '';

    #[Computed]
    public function applications()
    {
        return Application::where('organization_id', Auth::user()->organization_id)->with(['candidate', 'jobOpening'])
            ->when($this->job !== '', fn ($query) => $query->where('job_opening_id', $this->job))
            ->when(trim($this->search) !== '', fn ($query) => $query->whereHas('candidate', fn ($people) => $people->where('name', 'like', '%'.mb_substr(trim($this->search), 0, 100).'%')))
            ->latest('updated_at')->get();
    }

    #[Computed]
    public function jobs()
    {
        return JobOpening::where('organization_id', Auth::user()->organization_id)->orderBy('title')->get();
    }

    #[Computed]
    public function stats(): array
    {
        $base = Application::where('organization_id', Auth::user()->organization_id);

        return ['active' => (clone $base)->whereNotIn('stage', ['hired', 'rejected'])->count(), 'interviews' => Interview::where('organization_id', Auth::user()->organization_id)->where('status', 'scheduled')->where('starts_at', '>=', now())->count(), 'offers' => (clone $base)->where('stage', 'offer')->count(), 'hired' => (clone $base)->where('stage', 'hired')->count()];
    }

    public function render()
    {
        return view('livewire.pipeline')->layout('components.layouts.app');
    }
}
