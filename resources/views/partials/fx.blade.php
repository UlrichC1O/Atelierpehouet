{{-- Decorative interaction layer (fx.js): scroll progress, triangle cursor, back-to-top. --}}
<div class="scroll-progress" data-scroll-progress aria-hidden="true"><span class="scroll-progress__bar"></span></div>
<div class="cursor" data-cursor aria-hidden="true"><span class="cursor__tri"></span></div>
<button class="back-to-top" type="button" data-back-to-top aria-label="{{ __('ui.a11y.back_to_top') }}">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M12 3 21 19H3Z" fill="none" stroke="#f8d449" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M12 9.5 16.5 17h-9Z" fill="#e8433b"/>
    </svg>
</button>
