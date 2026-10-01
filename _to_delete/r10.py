import sys
p=sys.argv[1]; s=open(p,encoding='utf-8').read()
a="        .lp-brand-name { font-size: clamp(1.1rem, 1.25vw, 1.35rem); font-weight: 700; letter-spacing: 0.04em; }\n"
assert s.count(a)==1
s=s.replace(a,a+"""        /* Partner logos beside LYNC: a thin divider, then the PUP seal and the PUP TBIDO mark. */
        .lp-brand-group { display: flex; align-items: center; gap: clamp(0.6rem, 1.1vw, 1.1rem); flex-shrink: 0; }
        .lp-brand-sep { width: 1px; height: clamp(26px, 2.2vw, 36px); background: rgba(255,255,255,.45); }
        .lp-partners { display: flex; align-items: center; gap: clamp(0.2rem, 0.4vw, 0.4rem); }
        .lp-partners img { height: clamp(36px, 3vw, 50px); width: auto; object-fit: contain; filter: drop-shadow(0 2px 6px rgba(0,0,0,.25)); }
        @media (max-width: 380px) { .lp-brand-sep, .lp-partners { display: none; } }
""")
old='''                    <a href="{{ route('welcome') }}" class="lp-brand">
                        <span class="lp-brand-mark">
                            <img src="{{ asset('images/login-signup/lync-logo.png') }}" alt="">
                        </span>
                        <span class="lp-brand-name">LYNC</span>
                    </a>
'''
assert s.count(old)==1, 'brand'
new='''                    <div class="lp-brand-group">
                        <a href="{{ route('welcome') }}" class="lp-brand">
                            <span class="lp-brand-mark">
                                <img src="{{ asset('images/login-signup/lync-logo.png') }}" alt="">
                            </span>
                            <span class="lp-brand-name">LYNC</span>
                        </a>
                        <span class="lp-brand-sep" aria-hidden="true"></span>
                        <span class="lp-partners">
                            <img src="{{ asset('images/logo/pup-seal.png') }}" alt="Polytechnic University of the Philippines">
                            <img src="{{ asset('images/logo/pup-tbido.png') }}" alt="PUP TBIDO">
                        </span>
                    </div>
'''
s=s.replace(old,new)
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok')
