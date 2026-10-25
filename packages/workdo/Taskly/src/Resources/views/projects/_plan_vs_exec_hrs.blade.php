{{-- resources/views/projects/show.blade.php --}}
<div class="row mt-3">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Estimated hours</h6>
        <div class="h4 mb-0">
          {{ number_format($project->estimated_hours ?? 0, 2) }}
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Worked hours</h6>
        <div class="h4 mb-0">
          {{ number_format($project->actual_hours, 2) }}
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Difference</h6>
        <div class="h4 mb-0">
          @php $variance = ($project->estimated_hours ?? 0) - ($project->actual_hours ?? 0); @endphp
    
          @if (is_null($variance))
            —
          @else
            <span class="{{ $variance < 0 ? 'text-danger' : '' }}">
              {{ number_format($variance, 2) }} h
            </span>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
