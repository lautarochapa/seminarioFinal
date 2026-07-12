import { router } from 'expo-router';
import { goBackOrHome } from '../src/utils/navigation';

jest.mock('expo-router', () => ({
  router: {
    canGoBack: jest.fn(),
    back: jest.fn(),
    replace: jest.fn(),
  },
}));

const mockRouter = router as jest.Mocked<typeof router>;

beforeEach(() => {
  jest.clearAllMocks();
});

describe('goBackOrHome', () => {
  it('calls router.back() when history exists', () => {
    (mockRouter.canGoBack as jest.Mock).mockReturnValue(true);
    goBackOrHome();
    expect(mockRouter.back).toHaveBeenCalledTimes(1);
    expect(mockRouter.replace).not.toHaveBeenCalled();
  });

  it('calls router.replace("/(app)") when no history', () => {
    (mockRouter.canGoBack as jest.Mock).mockReturnValue(false);
    goBackOrHome();
    expect(mockRouter.replace).toHaveBeenCalledWith('/(app)');
    expect(mockRouter.back).not.toHaveBeenCalled();
  });
});
