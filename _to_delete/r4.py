import sys
p=sys.argv[1]
s=open(p,encoding='utf-8').read()
R=[
("#hero { min-height: 64.4vw; }","#hero { min-height: 56vw; }"),
(".hero-body { margin-top: clamp(2.5rem, 9.5vw, 10rem);",".hero-body { margin-top: clamp(2rem, 6vw, 6.5rem);"),
("padding-top: clamp(2.5rem, 4.5vw, 4.5rem);\n            clip-path","padding-top: clamp(2rem, 3vw, 3rem);\n            clip-path"),
(".cohort-tabs { position: relative; margin-top: clamp(2rem, 4.5vw, 4.5rem);",".cohort-tabs { position: relative; margin-top: clamp(1.5rem, 3vw, 3rem);"),
]
for a,b in R:
    assert s.count(a)==1,a; s=s.replace(a,b)
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok',p)
s=open(p,encoding='utf-8').read()
a="const BELT_Y = 975; "
assert s.count(a)==1; s=s.replace(a,"const BELT_Y = 900; ")
open(p,'w',encoding='utf-8',newline='\n').write(s); print('belt ok')
