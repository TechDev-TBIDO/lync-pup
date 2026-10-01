import sys,re
p=sys.argv[1]; aur=open(sys.argv[2],encoding='utf-8').read()
aur=re.sub(r' xmlns:c2pa="[^"]*"><metadata>.*?</metadata>','>',aur,flags=re.S).strip()
s=open(p,encoding='utf-8').read()
art=re.search(r'        <img id="hero-art"[^\n]*\n',s).group(0)
ind='\n'.join('        '+l if l.strip() else l for l in aur.splitlines())
new=('''        {{-- Hero artwork is built from layers so the sky effects can live in code:
               1. hero-sky.jpg     - the plain sky gradient (tiny file, stretched)
               2. #hero-aurora     - the aurora glows, light ribbons, corner tab and dot grid (SVG below)
               3. landing-hero.webp - the photo with the sky cut out (transparent)
               4. #hero-icons      - the three badges and their arc (SVG further down)
             All four share the 1728x903 coordinate space and get the same box from fitHeroArt(). --}}
        <img id="hero-sky" src="{{ asset('images/landing/hero-sky.jpg') }}" alt="" aria-hidden="true">
''' + ind + '\n' +
'''        <img id="hero-art" src="{{ asset('images/landing/landing-hero.webp') }}" alt="" aria-hidden="true">\n''')
s=s.replace(art,new,1)
# CSS
a="        #hero-icons { pointer-events: none;"
assert s.count(a)==1
s=s.replace(a,'''        #hero-sky, #hero-aurora { pointer-events: none; user-select: none; position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        #hero-aurora { overflow: hidden; }
        #hero.hero-stacked #hero-sky, #hero.hero-stacked #hero-aurora {
            -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 120px);
            mask-image: linear-gradient(to bottom, transparent 0, #000 120px);
        }
'''+a,1)
mot="            #hero.hero-idle #hero-icons .hi-arc, #hero.hero-idle #hero-icons .hi-node { animation: none; }"
assert s.count(mot)==1
s=s.replace(mot,mot+'''
            /* Aurora: the glows drift and breathe slowly, the ribbons shimmer a little. */
            @keyframes ha-drift-1 { 0%, 100% { transform: translate(0, 0); opacity: 1; } 50% { transform: translate(60px, -18px); opacity: 0.65; } }
            @keyframes ha-drift-2 { 0%, 100% { transform: translate(0, 0); opacity: 0.8; } 50% { transform: translate(-50px, 14px); opacity: 1; } }
            @keyframes ha-shimmer { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
            @keyframes ha-in { from { opacity: 0; } }
            #hero-aurora { animation: ha-in 1.6s ease-out backwards; }
            #hero-aurora .ha-glow-1 { animation: ha-drift-1 14s ease-in-out infinite; }
            #hero-aurora .ha-glow-2 { animation: ha-drift-2 18s ease-in-out infinite; }
            #hero-aurora .ha-ribbons { animation: ha-shimmer 9s ease-in-out infinite; }
            #hero.hero-idle #hero-aurora, #hero.hero-idle #hero-aurora .ha-glow, #hero.hero-idle #hero-aurora .ha-ribbons { animation: none; }''')
# JS
a2="            const icons = document.getElementById('hero-icons');"
assert s.count(a2)==1
s=s.replace(a2,a2+"\n            const layers = [document.getElementById('hero-sky'), document.getElementById('hero-aurora')];")
a3="""                if (icons) {"""
assert s.count(a3)==1
s=s.replace(a3,"""                layers.forEach(function (el) {
                    if (el) el.style.cssText = 'position:absolute;right:auto;bottom:auto;max-width:none;object-fit:fill;'
                        + 'width:' + w + 'px;height:' + h + 'px;left:' + left + 'px;top:' + top + 'px;';
                });
"""+a3,1)
old=re.search(r"const TOP_STOPS = \[.*?\];",s).group(0)
s=s.replace(old,"const TOP_STOPS = [[0.02, '#74070f'], [0.2, '#60050b'], [0.4, '#510308'], [0.55, '#4a0307'], [0.7, '#440507'], [0.85, '#400607'], [0.98, '#3d0908']];")
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok')
