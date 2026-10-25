<div class="form-group">
  <label for="estimated_hours" class="form-label">
    Estmated Hours (optional)
  </label>
  <input
    type="number"
    step="0.25"
    min="0"
    class="form-control"
    id="estimated_hours"
    name="estimated_hours"
    value="{{ old('estimated_hours', isset($task) ? $task->estimated_hours : '') }}"
    placeholder="Ex: 120.00">
  <small class="text-muted">You can use increments of 0.25 (¼ hour).</small>
  @error('estimated_hours')
    <div class="text-danger small">{{ $message }}</div>
  @enderror
</div>
