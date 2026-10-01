import sys
p=sys.argv[1]
s=open(p,encoding='utf-8').read()
R=[
(".hero-title { font-size: clamp(2rem, 4.6vw, 5rem);",".hero-title { font-size: clamp(1.75rem, 3.6vw, 3.75rem);"),
("font-size: clamp(0.875rem, 1.45vw, 1.6rem); font-weight: 500; line-height: 1.05;","font-size: clamp(0.85rem, 1.1vw, 1.2rem); font-weight: 500; line-height: 1.2;"),
("width: min(100%, max(20rem, 36vw));","width: min(100%, clamp(18rem, 28vw, 30rem));"),
(".hero-stats .num { font-size: clamp(1.5rem, 2.3vw, 2.5rem);",".hero-stats .num { font-size: clamp(1.35rem, 1.75vw, 1.9rem);"),
(".hero-stats .lbl { margin-top: 0.15rem; font-size: clamp(0.7rem, 0.95vw, 1rem);",".hero-stats .lbl { margin-top: 0.15rem; font-size: clamp(0.7rem, 0.75vw, 0.85rem);"),
("padding: clamp(0.5rem, 0.9vw, 1rem) 0; }","padding: clamp(0.5rem, 0.7vw, 0.8rem) 0; }"),
(".lp-footer { color: #fff; padding: clamp(2.5rem, 4.5vw, 4.5rem) 0;",".lp-footer { color: #fff; padding: clamp(2.25rem, 3.5vw, 3.5rem) 0;"),
(".lp-footer h3 { font-size: clamp(1.35rem, 1.9vw, 2rem);",".lp-footer h3 { font-size: clamp(1.15rem, 1.35vw, 1.5rem);"),
(".lp-footer .lead { margin-top: 1.25rem; max-width: 36rem; font-size: clamp(0.85rem, 1.1vw, 1.1rem);",".lp-footer .lead { margin-top: 1rem; max-width: 32rem; font-size: clamp(0.8rem, 0.85vw, 0.95rem);"),
(".lp-footer .copy { margin-top: 1.75rem; font-size: clamp(0.75rem, 0.95vw, 0.95rem);",".lp-footer .copy { margin-top: 1.25rem; font-size: clamp(0.72rem, 0.75vw, 0.82rem);"),
(".lp-footer ul { margin-top: 1.25rem; display: flex; flex-direction: column; gap: 0.95rem; font-size: clamp(0.85rem, 1.1vw, 1.1rem);",".lp-footer ul { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.7rem; font-size: clamp(0.8rem, 0.85vw, 0.95rem);"),
]
for a,b in R:
    assert s.count(a)==1,a; s=s.replace(a,b)
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok',p)
