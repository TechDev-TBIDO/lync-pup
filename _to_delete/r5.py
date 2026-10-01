import sys
p=sys.argv[1]
s=open(p,encoding='utf-8').read()
R=[
(".lp-footer { color: #fff; padding: clamp(2.25rem, 3.5vw, 3.5rem) 0;",".lp-footer { color: #fff; padding: clamp(1.5rem, 2.2vw, 2.25rem) 0;"),
(".lp-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }",".lp-footer-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }"),
("grid-template-columns: 1.75fr 0.8fr 1fr; gap: 3rem;","grid-template-columns: 1.6fr 0.8fr 1fr; gap: 2rem;"),
(".lp-footer h3 { font-size: clamp(1.15rem, 1.35vw, 1.5rem);",".lp-footer h3 { font-size: clamp(1rem, 1.1vw, 1.2rem); line-height: 1.3;"),
(".lp-footer .lead { margin-top: 1rem; max-width: 32rem; font-size: clamp(0.8rem, 0.85vw, 0.95rem);",".lp-footer .lead { margin-top: 0.5rem; max-width: 30rem; text-wrap: pretty; font-size: clamp(0.78rem, 0.8vw, 0.875rem);"),
(".lp-footer .copy { margin-top: 1.25rem; font-size: clamp(0.72rem, 0.75vw, 0.82rem);",".lp-footer .copy { margin-top: 0.75rem; font-size: clamp(0.7rem, 0.7vw, 0.78rem);"),
(".lp-footer ul { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.7rem; font-size: clamp(0.8rem, 0.85vw, 0.95rem);",".lp-footer ul { margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.35rem; font-size: clamp(0.78rem, 0.8vw, 0.875rem);"),
]
for a,b in R:
    assert s.count(a)==1,a; s=s.replace(a,b)
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok',p)
