export interface Album {
  id: string;
  title: string;
  info: string;
  thumbnail: string | null;
  images: string[];
  imageNames: string[];
}
