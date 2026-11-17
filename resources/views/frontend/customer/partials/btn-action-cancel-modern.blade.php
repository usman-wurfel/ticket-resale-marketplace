@php
  $label = $title ?? __('Cancel');
  $isCompact = !empty($compact ?? false);
  $confirm = $confirm ?? __('Are you sure you want to cancel your reservation?');
@endphp
<form action="{{ $action }}" method="POST" class="d-inline mb-0"
  onsubmit="return confirm(@json($confirm));">
  @csrf
  <button type="submit"
    class="btn-action-modern btn-action-modern--warning{{ $isCompact ? ' btn-action-modern--sm' : '' }}"
    title="{{ $label }}"
    aria-label="{{ $label }}">
    <span class="btn-action-modern__icon" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <path d="m15 9-6 6" />
        <path d="m9 9 6 6" />
      </svg>
    </span>
  </button>
</form>
