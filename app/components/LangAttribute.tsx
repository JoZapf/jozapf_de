// app/components/LangAttribute.tsx
'use client';

import { usePathname } from 'next/navigation';
import { useEffect } from 'react';

export default function LangAttribute() {
  const pathname = usePathname();

  useEffect(() => {
    const locale = pathname?.startsWith('/en') ? 'en' : 'de';
    if (document.documentElement.lang !== locale) {
      document.documentElement.lang = locale;
    }
  }, [pathname]);

  return null;
}
