@props(['league', 'user', 'textSize' => 'text-base'])

{{--
    League-tier photo frame: the ornate frame PNG (public/images/league/{tier}.png)
    overlaid on a circular photo, centered in the frame's transparent hole.
    Position (50%/48%) was measured directly off the artwork - every tier's
    hole sits at roughly the same spot. Size (52%) intentionally runs larger
    than the tightest hole (diamond) so the photo reads clearly - the
    photo div comes FIRST in source order (painted first/underneath) and the
    frame img comes SECOND (painted on top), so the sliver that would
    overlap the cap/ribbon/wreath sits behind that opaque artwork instead of
    poking out past it. Caller supplies a positioned ancestor (e.g.
    class="relative h-40 w-40") for these absolutely positioned layers to
    fill.
--}}
<div
    class="absolute overflow-hidden rounded-full border border-white/20 bg-gradient-to-br from-emerald-400 to-sky-400 {{ $textSize }} font-black shadow-inner"
    style="left:50%;top:48%;width:52%;height:52%;transform:translate(-50%,-50%)"
>
    <div class="flex h-full w-full items-center justify-center">
        @if ($user->photo_path)
            <img src="{{ $user->photoUrl() }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
        @else
            {{ strtoupper(mb_substr($user->name ?: 'U', 0, 1)) }}
        @endif
    </div>
</div>
<img
    src="{{ asset('images/league/'.strtolower($league).'.png') }}"
    alt="League {{ $league }}"
    class="pointer-events-none absolute inset-0 h-full w-full select-none"
    loading="lazy"
>
