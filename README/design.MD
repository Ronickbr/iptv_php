# KMKZ IPTV Design System

This document outlines the design system used in the KMKZ IPTV project, providing guidelines and specifications for maintaining visual consistency across the application.

## Color Palette

### Primary Colors
- **Purple Primary**: `#9333ea` (purple-600)
- **Purple Hover**: `#7c3aed` (purple-700)
- **Pink Accent**: `#ec4899` (pink-500)
- **Pink Hover**: `#db2777` (pink-600)

### Background Colors
- **Main Background**: `bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900`
- **Section Background**: `bg-black/30` (30% opacity black)
- **Card Background**: `bg-white/5 backdrop-blur-sm` (5% opacity white with blur)
- **Header Background**: `bg-black/20 backdrop-blur-sm` (20% opacity black with blur)

### Text Colors
- **Primary Text**: `text-white`
- **Secondary Text**: `text-gray-300`
- **Muted Text**: `text-gray-400`
- **Accent Text**: `text-purple-400`

### Border Colors
- **Primary Border**: `border-white/10` (10% opacity white)
- **Accent Border**: `border-purple-400`

## Typography

### Font Sizes
- **Hero Title**: `text-5xl md:text-7xl` (responsive large titles)
- **Section Title**: `text-4xl` (section headings)
- **Card Title**: `text-xl` (card headings)
- **Body Text**: `text-xl` (main content)
- **Small Text**: `text-sm` (footer, captions)

### Font Weights
- **Bold**: `font-bold` (titles and emphasis)
- **Medium**: `font-medium` (secondary headings)
- **Regular**: Default weight for body text

## Reusable Components

### Button Styles
```tsx
// Primary Button (CTA)
className="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white"

// Secondary Button (Outline)
className="border-purple-400 text-purple-400 hover:bg-purple-400 hover:text-white"

// Header Button
className="bg-purple-600 hover:bg-purple-700 text-white"
```

### Card Styles
```tsx
// Primary Card
className="bg-white/5 backdrop-blur-sm border-white/10 hover:bg-white/10 transition-all duration-300"

// Featured Card (with ring)
className="bg-white/5 backdrop-blur-sm border-white/10 hover:scale-105 transition-all duration-300 ring-2 ring-purple-400"
```

## Layout Structure

### Container Pattern
```tsx
<div className="container mx-auto px-4">
  {/* Content */}
</div>
```

### Section Spacing
- **Standard Section**: `py-20` (80px top/bottom padding)
- **Compact Section**: `py-16` (64px top/bottom padding)

### Grid Layouts
- **Features Grid**: `grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6`
- **Pricing Grid**: `grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6`
- **Testimonials Grid**: `grid-cols-1 md:grid-cols-3 gap-6`
- **Stats Grid**: `grid-cols-1 md:grid-cols-4 gap-8`

## Component Specifications

### Header
- **Background**: Semi-transparent with backdrop blur
- **Height**: Responsive (h-8 md:h-12 lg:h-16 for logo)
- **Position**: `sticky top-0 z-50`

### Hero Section
- **Text Gradient**: `text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-400`
- **Max Width**: `max-w-4xl mx-auto` for content container
- **Max Width Text**: `max-w-2xl mx-auto` for description

### Stats Section
- **Number Color**: `text-purple-400`
- **Number Size**: `text-4xl font-bold`
- **Description**: `text-gray-300`

### Features Section
- **Icon Color**: `text-green-400` (for checkmarks)
- **Icon Size**: `w-8 h-8`

### Pricing Cards
- **Popular Badge**: `bg-gradient-to-r from-purple-600 to-pink-600`
- **Badge Position**: `absolute -top-3 left-1/2 transform -translate-x-1/2`
- **Price Color**: `text-purple-400`
- **Price Size**: `text-3xl font-bold`

### Loyalty Program
- **Icon Background**: `bg-gradient-to-r from-yellow-400 to-orange-500`
- **Icon Container**: `rounded-full w-16 h-16`
- **Points Color**: Various (yellow-400, green-400, purple-400)

### Testimonials
- **Star Rating**: `text-yellow-400 text-xl`
- **Quote Style**: `italic` with quotes

## Interaction Patterns

### Hover Effects
- **Cards**: `hover:bg-white/10` or `hover:scale-105`
- **Buttons**: Color transitions with `transition-colors`
- **Scale Animation**: `hover:scale-105 transition-all duration-300`

### Transitions
- **Standard**: `transition-all duration-300`
- **Color**: `transition-colors`
- **Transform**: `transition-transform duration-200`

## Visual Elements

### Badges
- **Popular Badge**: Gradient background with white text
- **Savings Badge**: `text-green-400` for positive messaging

### Icons
- **Success Icons**: `text-green-400` (checkmarks)
- **Feature Icons**: `text-purple-400` or themed colors
- **Size**: Generally `w-8 h-8` for feature icons

### Gradients
- **Text Gradient**: Purple to pink for emphasis
- **Button Gradient**: Purple to pink for primary actions
- **Background Gradient**: Slate to purple for main background

## Responsive Design

### Breakpoints
- **Mobile**: Default styles
- **Tablet**: `md:` prefix (768px+)
- **Desktop**: `lg:` prefix (1024px+)

### Logo Sizing
- **Mobile**: `h-8`
- **Tablet**: `h-12`
- **Desktop**: `h-16`

### Text Scaling
- **Hero**: `text-5xl md:text-7xl`
- **Responsive Grids**: Collapse to single column on mobile

## Usage Guidelines

### Color Usage
1. Use the gradient background for the main page container
2. Apply semi-transparent backgrounds with backdrop blur for overlays
3. Use purple/pink gradients sparingly for emphasis and CTAs
4. Maintain consistent text color hierarchy (white > gray-300 > gray-400)

### Component Consistency
1. All cards should use the standard backdrop blur pattern
2. Buttons should follow the established gradient patterns
3. Maintain consistent spacing with the py-20 section pattern
4. Use the container mx-auto px-4 pattern for content width

### Accessibility Considerations
1. Ensure sufficient contrast between text and backgrounds
2. Use semantic HTML elements
3. Provide alt text for images
4. Maintain focus states for interactive elements

### Performance Notes
1. Use backdrop-blur-sm instead of heavy blur effects
2. Optimize gradient usage to avoid performance issues
3. Use loading="lazy" for images below the fold
4. Maintain smooth transitions without overusing animations

## File Structure

### Component Organization
- `/components/` - Reusable UI components
- `/components/ui/` - Shadcn/ui components
- `/pages/` - Page-level components

### Styling Approach
- Utility-first with Tailwind CSS
- Component-scoped styling
- Consistent use of CSS custom properties through Tailwind config

This design system should be referenced when creating new components or modifying existing ones to ensure visual consistency across the KMKZ IPTV application.
