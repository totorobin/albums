export interface Album {
  id: string;
  title: string;
  info: string;
  thumbnail: string | null;
  images: string[];
  imageNames: string[];
  thumbs: (string | null)[];
  hasThumbs: boolean;
  date: string | null;
  author: string | null;
  lieu: string | null;
}
