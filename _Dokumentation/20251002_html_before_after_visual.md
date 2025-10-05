# HTML Optimierung - Visueller Vergleich

## 📐 Struktur-Vergleich

### **VORHER - Flache, unsemantische Struktur**
```
html
└── body
    ├── header
    │   └── nav
    ├── section (hero) ❌ Sollte in <main>
    ├── main (horizontal timeline) ❌ Erstes <main>
    ├── main (vertical timeline) ❌ Zweites <main> - Invalid!
    ├── div (contact) ❌ Sollte in <main>
    └── footer
    
🔴 Probleme:
- 2x <main> (invalid HTML)
- Keine klare Haupt-Content-Struktur
- Hero außerhalb von <main>
```

### **NACHHER - Semantische, hierarchische Struktur**
```
html
└── body
    ├── a.visually-hidden-focusable (skip link) ✅ Accessibility
    ├── header
    │   └── nav[aria-label]
    ├── main#main-content ✅ Ein <main> für alles
    │   ├── section (hero)
    │   ├── section[aria-label] (horizontal timeline)
    │   │   └── article × 6 ✅ Semantic
    │   ├── section[aria-label] (vertical timeline)
    │   │   └── article × 6 ✅ Semantic
    │   └── div (contact)
    ├── footer
    └── aside[role=dialog] (privacy modal) ✅ Semantic
    
🟢 Verbesserungen:
- 1x <main> (valid)
- Klare Content-Hierarchie
- Semantic HTML5
- Accessibility-ready
```

---

## 🖼️ Timeline-Item Vergleich

### **VORHER**
```html
<div class="milestone-item">
  <div class="timeline-dot"></div>
  <div class="milestone-content">
    <div class="event-date">1999</div>
    <h6 class="track">Graphic</h6>
    <p