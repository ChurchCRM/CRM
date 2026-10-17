const clipsOverflow = (el: Element): boolean => {
  const { overflowX, overflowY } = getComputedStyle(el);
  return overflowX !== "visible" || overflowY !== "visible";
};

const hasClippingAncestor = (toggle: Element): boolean => {
  for (let el = toggle.parentElement; el && el !== document.body; el = el.parentElement) {
    if (clipsOverflow(el)) {
      return true;
    }
  }
  return false;
};

let current: { toggle: HTMLElement; menu: HTMLElement } | null = null;

const place = (): void => {
  if (!current) {
    return;
  }
  const { toggle, menu } = current;
  const rect = toggle.getBoundingClientRect();
  const left = menu.classList.contains("dropdown-menu-end") ? rect.right - menu.offsetWidth : rect.left;
  const below = rect.bottom + menu.offsetHeight <= window.innerHeight;
  const top = below || rect.top < menu.offsetHeight ? rect.bottom : rect.top - menu.offsetHeight;
  menu.style.left = `${Math.max(0, left)}px`;
  menu.style.top = `${top}px`;
};

const release = (): void => {
  if (!current) {
    return;
  }
  current.menu.classList.remove("dropdown-menu-fixed");
  current.menu.style.top = "";
  current.menu.style.left = "";
  current = null;
  window.removeEventListener("resize", place);
  window.removeEventListener("scroll", place, true);
};

// Bootstrap fires these on the toggle and they bubble. A menu inside an overflow
// container is switched to position:fixed so the container cannot clip it.
document.addEventListener("shown.bs.dropdown", (event) => {
  const toggle = event.target as HTMLElement;
  const menu = toggle.parentElement?.querySelector<HTMLElement>(".dropdown-menu");
  if (!menu || !hasClippingAncestor(toggle)) {
    return;
  }
  release();
  current = { toggle, menu };
  menu.classList.add("dropdown-menu-fixed");
  place();
  window.addEventListener("resize", place);
  window.addEventListener("scroll", place, true);
});

document.addEventListener("hide.bs.dropdown", release);
