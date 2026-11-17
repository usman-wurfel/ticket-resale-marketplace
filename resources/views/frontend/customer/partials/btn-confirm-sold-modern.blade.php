@php
  $confirmMsg = $confirm ?? __('Confirm payment received and mark as sold?');
@endphp
<form action="{{ $action }}" method="POST" class="mb-0 w-100" onsubmit="return confirm(@json($confirmMsg));">
  @csrf
  <button type="submit" class="btn-confirm-sold-modern">
    <span class="btn-confirm-sold-modern__icon" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
      </svg>
    </span>
    {{ __('Confirm sold') }}
  </button>
</form>
