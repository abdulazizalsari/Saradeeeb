# Saradeeeb development rules

- Mobile first is the acceptance criterion for every UI change.
- Start at 320px and verify 360px, 390px, 430px, 768px, 1024px and 1440px.
- Test both RTL and LTR, light and dark themes, keyboard focus, touch targets and reduced motion.
- Do not introduce horizontal scrolling, clipped text, overlapping controls, inaccessible dialogs or desktop-only workflows.
- Preserve existing branding, article/reflection card designs, routes, permissions and features unless the task explicitly changes them.
- After every HTML, PHP view, CSS or JavaScript UI change, run the relevant PHP lint/tests and perform a mobile browser check when available.
- Report tested viewport sizes, results, remaining issues and any checks that could not be performed.
