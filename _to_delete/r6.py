import sys,re
p=sys.argv[1]
s=open(p,encoding='utf-8').read()
R=[
("#hero { min-height: 56vw; }","#hero { min-height: 50vw; }"),
("--people-h: min(47vw, 420px);","--people-h: min(42.6vw, 386px);"),
("const IMG_W = 1728, IMG_H = 1121;","const IMG_W = 1728, IMG_H = 903;"),
("const BELT_Y = 950; ","const BELT_Y = 730; "),
("[[0.02, '#920c16'], [0.2, '#69090e'], [0.4, '#3f070c'], [0.7, '#3c060b'], [0.98, '#3d070b']]","[[0.02, '#530607'], [0.2, '#56050a'], [0.4, '#570408'], [0.7, '#3f0405'], [0.98, '#400807']]"),
("background: #3d060b; color: #fff;","background: #4d0406; color: #fff;"),
]
for a,b in R:
    assert s.count(a)==1,a; s=s.replace(a,b)
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok',p)
