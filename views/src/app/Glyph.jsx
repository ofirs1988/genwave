import React from 'react';

/**
 * The line icons of the Genwave admin screens: the same set as the Agent and
 * the Chatbot (genwave-chatbot views/settings/kit/Glyph.jsx), one stroke
 * weight, currentColor. No emoji: WordPress turns those into pictures.
 */
const P = {
  page: <><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" /><path d="M14 3v5h5" /></>,
  post: <><path d="M4 5h16M4 10h16M4 15h10" /></>,
  write: <><path d="M15.5 3.5a2.1 2.1 0 0 1 3 3L8 17l-4 1 1-4Z" /><path d="M13.5 5.5 18 10" /></>,
  image: <><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="M21 15.5 16 10.5 5 20" /></>,
  gallery: <><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></>,
  cart: <><circle cx="9" cy="20" r="1.4" /><circle cx="18" cy="20" r="1.4" /><path d="M2 3h3l2.4 11.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 7H6" /></>,
  box: <><path d="M21 8 12 3 3 8v8l9 5 9-5Z" /><path d="m3 8 9 5 9-5M12 13v8" /></>,
  chart: <><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></>,
  home: <><path d="M3 10.5 12 3l9 7.5" /><path d="M6 9.5V21h12V9.5" /></>,
  palette: <><path d="M12 3a9 9 0 1 0 0 18c1 0 1.6-.7 1.6-1.5 0-.9-.8-1.4-.8-2.2 0-.8.6-1.3 1.4-1.3H16a5 5 0 0 0 5-5c0-4.4-4-8-9-8Z" /><circle cx="7.5" cy="11" r="1.1" /><circle cx="11" cy="7.5" r="1.1" /><circle cx="15.5" cy="9" r="1.1" /></>,
  link: <><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7" /><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7L12.2 19" /></>,
  search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></>,
  settings: <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H2a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 7.9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V2a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H22a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z" /></>,
  tool: <><path d="M14.7 6.3a4 4 0 0 1 5.3 5.3L18 13l-7 7-4 1 1-4 7-7Z" /></>,
  bolt: <><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z" /></>,
  check: <><path d="M20 6 9 17l-5-5" /></>,
  sun: <><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></>,
  moon: <><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z" /></>,
  warning: <><path d="M12 3 2.5 20h19Z" /><path d="M12 9.5v4.5M12 17.2h.01" /></>,
  error: <><circle cx="12" cy="12" r="9" /><path d="m15 9-6 6M9 9l6 6" /></>,
  info: <><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 7.8h.01" /></>,
  dot: <><circle cx="12" cy="12" r="4.5" /></>,
  globe: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18" /><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z" /></>,
  device: <><rect x="7" y="2.5" width="10" height="19" rx="2.5" /><path d="M11 18.5h2" /></>,
  user: <><circle cx="12" cy="8" r="3.5" /><path d="M4.5 20a7.5 7.5 0 0 1 15 0" /></>,
  clipboard: <><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4.5V3.5h6v1" /><path d="M9 10h6M9 14h4" /></>,
  puzzle: <><path d="M10 3h4v2.5a1.8 1.8 0 1 0 3.5 0V3H21v4h-2.5a1.8 1.8 0 1 0 0 3.5H21V21H10v-2.5a1.8 1.8 0 1 0-3.5 0V21H3V10h2.5a1.8 1.8 0 1 0 0-3.5H3V3h7Z" /></>,
  shield: <><path d="M12 3 4.5 6v6c0 4.4 3.1 8.3 7.5 9.3 4.4-1 7.5-4.9 7.5-9.3V6Z" /></>,
  clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5.2l3.2 1.9" /></>,
  mail: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3.5 6.5 8.5 6 8.5-6" /></>,
  star: <><path d="m12 3.5 2.6 5.6 6 .7-4.5 4 1.3 6-5.4-3.1L6.6 20l1.3-6-4.5-4 6-.7Z" /></>,
  sparkle: <><path d="M12 3.5 13.6 9 19 10.5 13.6 12 12 17.5 10.4 12 5 10.5 10.4 9Z" /><path d="M18.5 4v3M20 5.5h-3" /></>,
  rocket: <><path d="M12 3c3.5 2 5.5 5.5 5.5 9.5L12 18l-5.5-5.5C6.5 8.5 8.5 5 12 3Z" /><circle cx="12" cy="10" r="1.6" /><path d="M8.5 17 6 21l4-1.5M15.5 17l2.5 4-4-1.5" /></>,
  lock: <><rect x="4.5" y="10.5" width="15" height="9.5" rx="2" /><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" /></>,
  trash: <><path d="M4 7h16M10 4h4M6 7l1 13h10l1-13" /><path d="M10.5 11v6M13.5 11v6" /></>,
  folder: <><path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" /></>,
  layout: <><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18M9 21V9" /></>,
  chat: <><path d="M4 5h16a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1Z" /></>,
  code: <><path d="m9 8-5 4 5 4M15 8l5 4-5 4" /></>,
  eye: <><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" /></>,
  money: <><rect x="2.5" y="6" width="19" height="12" rx="2" /><circle cx="12" cy="12" r="2.6" /></>,
  flame: <><path d="M12 3c.5 3-2 4-2 7a2 2 0 0 0 4 0c0-1 .5-1.8 1-2.5 1.5 1.5 2.5 3.5 2.5 5.5a5.5 5.5 0 0 1-11 0C6.5 8.5 9 5.5 12 3Z" /></>,
  refresh: <><path d="M20.5 12a8.5 8.5 0 1 1-2.5-6" /><path d="M20.5 4v5h-5" /></>,
  tag: <><path d="M3 11.5V4a1 1 0 0 1 1-1h7.5L21 12.5 12.5 21Z" /><circle cx="7.5" cy="7.5" r="1.3" /></>,
  inbox: <><path d="M3 13h5l1.5 3h5L16 13h5" /><path d="M5.5 5h13l2.5 8v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4Z" /></>,
  briefcase: <><rect x="2.5" y="7" width="19" height="13" rx="2" /><path d="M8.5 7V5a1.5 1.5 0 0 1 1.5-1.5h4A1.5 1.5 0 0 1 15.5 5v2" /><path d="M2.5 12.5h19" /></>,
  store: <><path d="M4 9.5V20h16V9.5" /><path d="M3 5h18l1 4.5a3 3 0 0 1-5.5 1.7 3 3 0 0 1-5 0 3 3 0 0 1-5.5-1.7Z" /><path d="M9.5 20v-5.5h5V20" /></>,
  dining: <><path d="M7 3v7a2 2 0 0 0 4 0V3M9 10v11" /><path d="M17 3c-1.5 1.5-2 3-2 5s.7 3 2 3v10" /></>,
  compass: <><circle cx="12" cy="12" r="9" /><path d="m15.5 8.5-2 5.5-5.5 2 2-5.5Z" /></>,
  calendar: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></>,
  book: <><path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v15H5.5A1.5 1.5 0 0 0 4 19.5Z" /><path d="M4 19.5A1.5 1.5 0 0 1 5.5 21H19" /></>,
  question: <><circle cx="12" cy="12" r="9" /><path d="M9.6 9.3a2.5 2.5 0 1 1 3.4 2.4c-.7.3-1 .9-1 1.6v.3" /><path d="M12 17.2h.01" /></>,
  ruler: <><rect x="2" y="8" width="20" height="8" rx="1.5" /><path d="M7 8v3M12 8v4M17 8v3" /></>,
  key: <><circle cx="8" cy="12" r="4" /><path d="M12 12h9M18 12v3M15.5 12v2.5" /></>,
  pause: <><rect x="7" y="5" width="3.5" height="14" rx="1" /><rect x="13.5" y="5" width="3.5" height="14" rx="1" /></>,
  plus: <><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></>,
  bot: <><rect x="4" y="7.5" width="16" height="12" rx="3" /><path d="M12 3v4.5" /><circle cx="9" cy="13" r="1.2" /><circle cx="15" cy="13" r="1.2" /></>,
  plug: <><path d="M9 3v5M15 3v5" /><path d="M6.5 8h11v3a5.5 5.5 0 0 1-11 0Z" /><path d="M12 16.5V21" /></>,
  video: <><rect x="2.5" y="6" width="13" height="12" rx="2" /><path d="m15.5 10.5 6-3v9l-6-3Z" /></>,
  wave: <><path d="M2.5 9c2-2.5 4-2.5 6 0s4 2.5 6 0 4-2.5 6 0" /><path d="M2.5 15c2-2.5 4-2.5 6 0s4 2.5 6 0 4-2.5 6 0" /></>,
  play: <><path d="M8 5.5 18.5 12 8 18.5Z" /></>,
  menu: <><path d="M4 7h16M4 12h16M4 17h16" /></>,
  hash: <><path d="M9 3.5 7.5 20.5M16.5 3.5 15 20.5M4 9h16M3.5 15h16" /></>,
  minus: <><path d="M5 12h14" /></>,
  undo: <><path d="M4 9h11a5 5 0 0 1 0 10h-3" /><path d="M8 5 4 9l4 4" /></>,
  expandX: <><path d="M3 12h18" /><path d="m7 8-4 4 4 4M17 8l4 4-4 4" /></>,
  expandY: <><path d="M12 3v18" /><path d="m8 7 4-4 4 4M8 17l4 4 4-4" /></>,
  arrowUp: <><path d="M12 20V4" /><path d="m6 10 6-6 6 6" /></>,
  arrowOut: <><path d="M7 17 17 7" /><path d="M9 7h8v8" /></>,
  carousel: <><rect x="7" y="5" width="10" height="14" rx="2" /><path d="M3.5 8v8M20.5 8v8" /></>,
  copyright: <><circle cx="12" cy="12" r="9" /><path d="M14.8 9.6a3.5 3.5 0 1 0 0 4.8" /></>,
  brain: <><path d="M12 5.5a3 3 0 0 0-5.7 1.3A3 3 0 0 0 5 12a3 3 0 0 0 1.6 5.2 3 3 0 0 0 5.4.8Z" /><path d="M12 5.5a3 3 0 0 1 5.7 1.3A3 3 0 0 1 19 12a3 3 0 0 1-1.6 5.2 3 3 0 0 1-5.4.8Z" /><path d="M12 5.5V18" /></>,
  broom: <><path d="M14 3 8.5 8.5" /><path d="m7 7 4 4-4.5 4.5L4 21l5.5-2.5L14 14l-4-4Z" /></>,
  trophy: <><path d="M7 4h10v5a5 5 0 0 1-10 0Z" /><path d="M7 5.5H4.5v1a3.5 3.5 0 0 0 3 3.4M17 5.5h2.5v1a3.5 3.5 0 0 1-3 3.4" /><path d="M12 14v3.5M8.5 20.5h7" /></>,
  crown: <><path d="M3.5 7.5 7 13l5-7.5 5 7.5 3.5-5.5V18h-17Z" /></>,
  logout: <><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /><path d="m10 17 5-5-5-5" /><path d="M15 12H3" /></>,
  download: <><path d="M12 3v12" /><path d="m7 10 5 5 5-5" /><path d="M5 21h14" /></>,
  arrow: <><path d="M5 12h14" /><path d="m13 6 6 6-6 6" /></>,
  close: <><path d="M6 6l12 12M18 6 6 18" /></>,
  database: <><ellipse cx="12" cy="5" rx="8" ry="3" /><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5" /><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" /></>,
  copy: <><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></>,
};

export default function Glyph({ name, size = 16, className = '' }) {
    const paths = P[name];
    if (!paths) return null;
    return (
        <svg
            className={className}
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {paths}
        </svg>
    );
}
