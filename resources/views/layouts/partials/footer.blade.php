

<div class="tw-mt-auto no-print">
  <div class="tw-border-t tw-border-gray-200 vf-footer">
    <div class="tw-px-5 tw-py-3 tw-flex tw-flex-col tw-gap-1.5 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
      <p class="tw-mb-0 vf-footer-brand">
        <img src="{{ asset('img/vendify-logo-mark.png') }}" alt="{{ config('app.name') }} logo" />
        {{ config('app.name') }}
      </p>
      <p class="tw-mb-0 tw-text-xs tw-font-normal tw-text-gray-500">
        <span class="tw-font-mono tw-font-medium">V{{config('author.app_version')}}</span> | Copyright &copy; {{ date('Y') }} All rights reserved.
      </p>
    </div>
  </div>
</div>