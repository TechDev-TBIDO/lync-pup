import sys,re
p=sys.argv[1]; s=open(p,encoding='utf-8').read()
def cut(pat, repl=''):
    global s
    n=len(re.findall(pat,s,flags=re.S)); assert n==1,(pat,n); s=re.sub(pat,repl,s,flags=re.S)
# HTML layers
cut(r"        \{\{-- Hero artwork is built from layers.*?<img id=\"hero-art\" src=\"\{\{ asset\('images/landing/landing-hero.webp'\) \}\}\" alt=\"\" aria-hidden=\"true\">\n",
    "        <img id=\"hero-art\" src=\"{{ asset('images/landing/landing-hero.jpg') }}\" alt=\"\" aria-hidden=\"true\">\n")
cut(r"        \{\{-- The three badges.*?</svg>\n")
# CSS
cut(r"        #hero-sky, #hero-aurora \{.*?        #hero-icons \{ pointer-events: none;.*?\n        \}\n")
cut(r"\n            /\* Badges: the arc draws itself in.*?#hero.hero-idle #hero-aurora \.ha-ribbons \{ animation: none; \}")
# JS
cut(r"\n            const icons = document.getElementById\('hero-icons'\);\n            const layers = .*?;")
cut(r"\n                layers.forEach\(function \(el\) \{.*?\n                \}\);\n                if \(icons\) \{.*?\n                \}")
cut(r"const TOP_STOPS = \[.*?\];","const TOP_STOPS = [[0.02, '#530607'], [0.2, '#56050a'], [0.4, '#570408'], [0.7, '#3f0405'], [0.98, '#400807']];")
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok')
for k in ['hero-icons','hero-aurora','hero-sky','hi-','ha-','webp','icons']: print(k, s.count(k))
