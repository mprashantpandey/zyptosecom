# Next.js Web Frontend Integration Guide

## Overview

The Next.js web storefront must consume the same remote configuration APIs as the Flutter app. All branding, themes, and features are fetched at runtime for dynamic rendering.

## Architecture Principles

1. **SSR for SEO** - Server-side render with remote config
2. **Remote Config Driven** - Same APIs as mobile app
3. **Dynamic Theming** - Apply theme from config
4. **Feature Flags** - Respect module and rule-based toggles
5. **Client-Side Hydration** - Hydrate with same config

## Remote Config Integration

### API Endpoint

```
GET /api/v1/config?platform=web
```

Same response structure as Flutter app (see `FLUTTER.md`).

## Implementation Guide

### 1. Remote Config Service

Create a config service for Next.js:

```typescript
// lib/services/remoteConfig.ts
interface RemoteConfigData {
  branding: BrandingConfig;
  theme: ThemeConfig;
  modules: Record<string, any>;
  app_management: AppManagementConfig;
  feature_flags: Record<string, any>;
  home_layout: HomeSection[];
  content_strings: Record<string, string>;
  timestamp: string;
}

class RemoteConfigService {
  private config: RemoteConfigData | null = null;
  private lastFetch: Date | null = null;
  private cacheTTL = 3600000; // 1 hour in ms
  
  async fetchConfig(version?: string, forceRefresh = false): Promise<RemoteConfigData> {
    // Check cache
    if (!forceRefresh && this.config && this.lastFetch) {
      const age = Date.now() - this.lastFetch.getTime();
      if (age < this.cacheTTL) {
        return this.config;
      }
    }
    
    const params = new URLSearchParams({ platform: 'web' });
    if (version) params.append('version', version);
    
    const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/config?${params}`);
    
    if (!response.ok) {
      if (this.config) return this.config; // Fallback to cache
      throw new Error('Failed to fetch remote config');
    }
    
    const data = await response.json();
    this.config = data.data;
    this.lastFetch = new Date();
    
    return this.config;
  }
  
  getConfig(): RemoteConfigData | null {
    return this.config;
  }
}

export const remoteConfigService = new RemoteConfigService();
```

### 2. Server-Side Config Fetching

Fetch config in `getServerSideProps` or `getStaticProps`:

```typescript
// pages/index.tsx or app/page.tsx
import { remoteConfigService } from '@/lib/services/remoteConfig';
import { GetServerSideProps } from 'next';

export const getServerSideProps: GetServerSideProps = async () => {
  try {
    const config = await remoteConfigService.fetchConfig();
    
    return {
      props: {
        config,
      },
    };
  } catch (error) {
    return {
      props: {
        config: null,
        error: 'Failed to load configuration',
      },
    };
  }
};

export default function HomePage({ config }: { config: RemoteConfigData }) {
  // Use config for rendering
  return (
    <div>
      <h1>{config.branding.app_name}</h1>
      {/* Render home layout from config */}
    </div>
  );
}
```

### 3. Dynamic Theme Provider

Create a theme provider that uses remote config:

```typescript
// lib/theme/ThemeProvider.tsx
'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { ThemeProvider as MUIThemeProvider, createTheme } from '@mui/material/styles';

interface ThemeContextType {
  theme: ThemeConfig;
  updateTheme: (newTheme: ThemeConfig) => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ 
  children, 
  initialTheme 
}: { 
  children: React.ReactNode;
  initialTheme: ThemeConfig;
}) {
  const [theme, setTheme] = useState(initialTheme);
  
  const muiTheme = createTheme({
    palette: {
      primary: {
        main: theme.primary_color,
      },
      secondary: {
        main: theme.secondary_color,
      },
      background: {
        default: theme.background_color,
        paper: theme.surface_color,
      },
      text: {
        primary: theme.text_color,
        secondary: theme.text_secondary_color,
      },
    },
    shape: {
      borderRadius: parseInt(theme.border_radius) || 8,
    },
    typography: {
      fontFamily: theme.font_family || undefined,
    },
  });
  
  return (
    <ThemeContext.Provider value={{ theme, updateTheme: setTheme }}>
      <MUIThemeProvider theme={muiTheme}>
        {children}
      </MUIThemeProvider>
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) throw new Error('useTheme must be used within ThemeProvider');
  return context;
}
```

### 4. Dynamic Home Layout Component

Render home sections from JSON config:

```typescript
// components/HomeLayout.tsx
interface HomeLayoutProps {
  sections: HomeSection[];
}

export function HomeLayout({ sections }: HomeLayoutProps) {
  return (
    <>
      {sections.map((section) => (
        <HomeSectionRenderer key={section.id} section={section} />
      ))}
    </>
  );
}

function HomeSectionRenderer({ section }: { section: HomeSection }) {
  switch (section.type) {
    case 'banner':
      return <BannerSection data={section.data} style={section.style} />;
    case 'products':
      return <ProductsSection data={section.data} style={section.style} />;
    case 'categories':
      return <CategoriesSection data={section.data} style={section.style} />;
    case 'grid':
      return <GridSection data={section.data} style={section.style} />;
    case 'carousel':
      return <CarouselSection data={section.data} style={section.style} />;
    default:
      return null;
  }
}

// Example: Banner Section
function BannerSection({ data, style }: { data: any; style: any }) {
  const images = data.images || [];
  const autoSlide = data.auto_slide ?? true;
  const duration = data.duration ?? 3000;
  
  return (
    <Carousel
      autoPlay={autoSlide}
      interval={duration}
      style={style}
    >
      {images.map((image: string, idx: number) => (
        <Box key={idx}>
          <Image src={image} alt={`Banner ${idx + 1}`} width={1200} height={400} />
        </Box>
      ))}
    </Carousel>
  );
}
```

### 5. Feature Flags Hook

Create a hook for feature flags:

```typescript
// hooks/useFeatureFlags.ts
'use client';

import { useContext } from 'react';
import { RemoteConfigContext } from '@/contexts/RemoteConfigContext';

export function useFeatureFlags() {
  const { config } = useContext(RemoteConfigContext);
  
  return {
    isEnabled: (key: string, defaultValue = false): boolean => {
      return config?.feature_flags[key] ?? defaultValue;
    },
    getValue: <T>(key: string, defaultValue: T): T => {
      return config?.feature_flags[key] ?? defaultValue;
    },
  };
}

// Usage
function CheckoutPage() {
  const { isEnabled, getValue } = useFeatureFlags();
  const codEnabled = isEnabled('payments.cod_enabled');
  const codMaxAmount = getValue<number>('payments.cod_max_amount', 5000);
  
  return (
    <div>
      {codEnabled && (
        <PaymentMethod 
          name="COD" 
          maxAmount={codMaxAmount} 
        />
      )}
    </div>
  );
}
```

### 6. Content Strings Helper

Create a helper for content strings with variable interpolation:

```typescript
// lib/utils/contentStrings.ts
export function getContentString(
  strings: Record<string, string>,
  key: string,
  variables?: Record<string, string>
): string {
  let text = strings[key] || key;
  
  if (variables) {
    Object.entries(variables).forEach(([varName, varValue]) => {
      text = text.replace(new RegExp(`{${varName}}`, 'g'), varValue);
    });
  }
  
  return text;
}

// Usage
const config = remoteConfigService.getConfig();
const welcomeMessage = getContentString(
  config.content_strings,
  'app.welcome_message',
  { app_name: config.branding.app_name }
);
```

### 7. Meta Tags from Config

Set dynamic meta tags based on config:

```typescript
// components/MetaTags.tsx
import Head from 'next/head';

export function MetaTags({ config }: { config: RemoteConfigData }) {
  return (
    <Head>
      <title>{config.branding.app_name}</title>
      <meta name="description" content={`${config.branding.app_name} - ${config.branding.company_name}`} />
      <link rel="icon" href={config.branding.favicon || '/favicon.ico'} />
      {config.theme.font_url && (
        <link rel="stylesheet" href={config.theme.font_url} />
      )}
      <style jsx global>{`
        :root {
          --primary-color: ${config.theme.primary_color};
          --secondary-color: ${config.theme.secondary_color};
          --accent-color: ${config.theme.accent_color};
          --background-color: ${config.theme.background_color};
          --text-color: ${config.theme.text_color};
          --border-radius: ${config.theme.border_radius};
        }
      `}</style>
    </Head>
  );
}
```

### 8. App Layout with Config

Wrap app with config provider:

```typescript
// app/layout.tsx or _app.tsx
import { RemoteConfigProvider } from '@/contexts/RemoteConfigContext';
import { ThemeProvider } from '@/lib/theme/ThemeProvider';
import { MetaTags } from '@/components/MetaTags';

export default function RootLayout({
  children,
  config,
}: {
  children: React.ReactNode;
  config: RemoteConfigData;
}) {
  return (
    <html>
      <head>
        <MetaTags config={config} />
      </head>
      <body>
        <RemoteConfigProvider initialConfig={config}>
          <ThemeProvider initialTheme={config.theme}>
            {children}
          </ThemeProvider>
        </RemoteConfigProvider>
      </body>
    </html>
  );
}
```

## Best Practices

1. **Fetch config on server-side** for SEO and initial render
2. **Cache config** to reduce API calls
3. **Fallback gracefully** if config fetch fails
4. **Use CSS variables** for dynamic theming
5. **Render home layout** from JSON structure
6. **Check feature flags** before showing features
7. **Use content strings** for all user-facing text
8. **Set dynamic meta tags** from config
9. **Handle maintenance mode** on server-side
10. **Optimize images** from remote URLs (next/image)

## Package Dependencies

```json
{
  "dependencies": {
    "next": "^14.0.0",
    "react": "^18.0.0",
    "@mui/material": "^5.0.0",
    "@mui/icons-material": "^5.0.0",
    "axios": "^1.6.0"
  }
}
```

## Environment Variables

```env
NEXT_PUBLIC_API_URL=https://api.example.com
```

## SEO Considerations

1. **Server-side render** all pages with config
2. **Dynamic meta tags** from branding config
3. **Structured data** for products/categories
4. **Sitemap generation** from catalog
5. **Robots.txt** configuration
6. **Open Graph** tags from config
7. **Canonical URLs** for all pages

## Performance Optimization

1. **Config caching** (1 hour TTL)
2. **Image optimization** (next/image)
3. **Static generation** where possible
4. **Incremental Static Regeneration** (ISR)
5. **Code splitting** by route
6. **Font optimization** from config
7. **CSS-in-JS** with theme from config

