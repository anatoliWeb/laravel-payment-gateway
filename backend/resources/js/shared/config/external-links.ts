const DEFAULT_DASHBOARD_URL = 'http://localhost:4200';

const normalizeBaseUrl = (value: string): string => value.trim().replace(/\/+$/, '');
const normalizePath = (value: string): string => `/${value.trim().replace(/^\/+/, '')}`;

/**
 * Cross-app links stay configurable so local Docker development can point Vue
 * Admin at the Angular dashboard without hardcoding production hosts.
 */
export const getDashboardBaseUrl = (): string => {
  const value = String(import.meta.env.VITE_DASHBOARD_URL ?? DEFAULT_DASHBOARD_URL).trim();
  return value ? normalizeBaseUrl(value) : DEFAULT_DASHBOARD_URL;
};

export const buildDashboardUrl = (path: string): string => {
  return `${getDashboardBaseUrl()}${normalizePath(path)}`;
};
