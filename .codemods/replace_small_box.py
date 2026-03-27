#!/usr/bin/env python3
import re,os,shutil,difflib
root='src'
exts=('.php','.phtml','.html','.tpl')
class_attr=re.compile(r'(class\s*=\s*["\'])([^"\']*)(["\'])',re.I)
mapping={'small-box':('card','card-sm')}
changed=[]
for dirpath,dirs,files in os.walk(root):
    for fn in files:
        if fn.endswith(exts):
            path=os.path.join(dirpath,fn)
            try:
                with open(path,'r',encoding='utf-8') as f:
                    text=f.read()
            except Exception:
                continue
            orig=text
            def repl(m):
                pre=m.group(1); cls=m.group(2); post=m.group(3)
                parts=cls.split()
                new_parts=[]
                changed_flag=False
                for p in parts:
                    if p in mapping:
                        new_parts.extend(mapping[p]); changed_flag=True
                    else:
                        new_parts.append(p)
                if changed_flag:
                    return pre + ' '.join(new_parts) + post
                return m.group(0)
            new=class_attr.sub(repl,text)
            if new!=orig:
                shutil.copy2(path,path+'.bak')
                with open(path,'w',encoding='utf-8') as f:
                    f.write(new)
                changed.append(path)
                diff=''.join(difflib.unified_diff(orig.splitlines(True),new.splitlines(True),fromfile=path,tofile=path))
                print('--- CHANGED:',path)
                print(diff)
if not changed:
    print('No files changed by small-box codemod.')
else:
    print('\nSummary: {} files changed.'.format(len(changed)))
