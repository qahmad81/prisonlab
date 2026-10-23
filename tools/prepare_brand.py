"""Build deterministic web/app icon derivatives of the approved PrisonLab logo.

The source art is never modified. Pillow is required; outputs are public assets.
"""
from pathlib import Path
from PIL import Image
import base64
import shutil

root = Path(__file__).resolve().parents[1]
source = root / 'assets/branding/prisonlab-logo.png'
reference = root / 'reference/originals' / source.name
reference.parent.mkdir(parents=True, exist_ok=True)
if not reference.exists():
    shutil.copy2(source, reference)
out = root / 'assets/branding'
with Image.open(source) as original:
    assert original.width == original.height and original.width >= 512
    image = original.convert('RGB')
    for size in [16, 32, 64, 128, 180, 192, 256, 512]:
        target = out / f'prisonlab-{size}.png'
        image.resize((size, size), Image.Resampling.LANCZOS).save(target, optimize=True)
        assert target.stat().st_size < source.stat().st_size
        with Image.open(target) as check:
            check.verify()
        print(f'{target.relative_to(root)}: {size}x{size}, {target.stat().st_size} bytes')
    image.save(root / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])
encoded = base64.b64encode((out / 'prisonlab-128.png').read_bytes()).decode('ascii')
(root / 'favicon.svg').write_text(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128">'
    f'<image width="128" height="128" href="data:image/png;base64,{encoded}"/></svg>\n',
    encoding='utf-8')
