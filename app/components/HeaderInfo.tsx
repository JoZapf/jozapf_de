'use client';

import { useEffect, useState } from 'react';

export default function HeaderInfo() {
    const [version, setVersion] = useState<string>('');
    const [buildDate, setBuildDate] = useState<string>('');

    useEffect(() => {
        // Fetch the summary.json when component mounts
        fetch('/summary.json')
            .then(res => res.json())
            .then(data => {
                setVersion(data.version);
                setBuildDate(new Date(data.last_updated).toLocaleDateString());
            })
            .catch(console.error);
    }, []);

    // Update the build info elements
    useEffect(() => {
        const versionEl = document.getElementById('build-version');
        const dateEl = document.getElementById('build-date');
        
        if (versionEl && version) {
            versionEl.textContent = version;
        }
        if (dateEl && buildDate) {
            dateEl.textContent = buildDate;
        }
    }, [version, buildDate]);

    return null;
}
