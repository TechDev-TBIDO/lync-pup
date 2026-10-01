import sys,re
p=sys.argv[1]; svg=open(sys.argv[2],encoding='utf-8').read().strip()
s=open(p,encoding='utf-8').read()
a=re.search(r'        <img id="hero-art"[^\n]*\n',s).group(0)
assert s.count(a)==1
ind='\n'.join('        '+l if l.strip() else l for l in svg.splitlines())
s=s.replace(a, a+'''        {{-- The three badges (team, idea, launch) and their arc, drawn as SVG on top of the artwork
             instead of being baked into it, so they stay crisp and can be moved/edited here. Same
             coordinate system as landing-hero.jpg (1728x903); fitHeroArt() gives it the image's box.
             Standalone copy: public/images/landing/hero-icons.svg --}}
'''+ind+'\n')
css_anchor="        #hero.hero-stacked #hero-art {"
assert s.count(css_anchor)==1
s=s.replace(css_anchor,'''        #hero-icons { pointer-events: none; position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
        #hero.hero-stacked #hero-icons {
            -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 120px);
            mask-image: linear-gradient(to bottom, transparent 0, #000 120px);
        }
'''+css_anchor,1)
mot="            #hero.hero-idle #hero-art, #hero.hero-idle .lp-nav, #hero.hero-idle .hero-body > * { animation: none; }"
assert s.count(mot)==1
s=s.replace(mot, mot+'''
            /* Badges: the arc draws itself in, the badges pop in one after another, then float gently. */
            @keyframes hi-draw { from { stroke-dashoffset: 1; } to { stroke-dashoffset: 0; } }
            @keyframes hi-pop { from { opacity: 0; transform: scale(0.4); } }
            @keyframes hi-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
            #hero-icons .hi-arc { stroke-dasharray: 1; animation: hi-draw 1.4s cubic-bezier(0.4, 0, 0.2, 1) 0.4s backwards; }
            #hero-icons .hi-node { transform-box: fill-box; transform-origin: center;
                animation: hi-pop 0.55s cubic-bezier(0.3, 1.5, 0.5, 1) backwards, hi-float 5s ease-in-out infinite; }
            #hero-icons > g:nth-of-type(1) .hi-node { animation-delay: 0.7s, 1.3s; }
            #hero-icons > g:nth-of-type(2) .hi-node { animation-delay: 1.0s, 2.1s; }
            #hero-icons > g:nth-of-type(3) .hi-node { animation-delay: 1.3s, 2.9s; }
            #hero.hero-idle #hero-icons .hi-arc, #hero.hero-idle #hero-icons .hi-node { animation: none; }''')
a2="            const art = document.getElementById('hero-art');"
assert s.count(a2)==1
s=s.replace(a2,a2+"\n            const icons = document.getElementById('hero-icons');")
a3="""                    + 'left:' + left + 'px;top:' + top + 'px;';"""
assert s.count(a3)==1
s=s.replace(a3,a3+"""
                if (icons) {
                    icons.style.cssText = 'position:absolute;right:auto;bottom:auto;'
                        + 'width:' + w + 'px;height:' + h + 'px;left:' + left + 'px;top:' + top + 'px;';
                }""")
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok')
