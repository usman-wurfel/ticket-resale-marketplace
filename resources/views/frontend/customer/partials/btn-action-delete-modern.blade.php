@php
  $label = $title ?? __('Delete');
  $isCompact = !empty($compact ?? false);
  $confirm = $confirm ?? __('Are you sure you want to delete this listing?');
@endphp
<form action="{{ $action }}" method="POST" class="d-inline mb-0"
  onsubmit="return confirm(@json($confirm));">
  @csrf
  @method('DELETE')
  <button type="submit"
    class="btn-action-modern btn-action-modern--danger{{ $isCompact ? ' btn-action-modern--sm' : '' }}"
    title="{{ $label }}"
    aria-label="{{ $label }}">
    <span class="btn-action-modern__icon" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 6h18" />
        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
        <line x1="10" x2="10" y1="11" y2="17" />
        <line x1="14" x2="14" y1="11" y2="17" />
      </svg>
    </span>
  </button>
</form>
